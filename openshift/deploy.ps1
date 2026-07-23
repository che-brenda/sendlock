# One-command SendLock deploy to the *current* OpenShift project (Windows
# mirror of deploy.sh):
#
#   .\openshift\deploy.ps1
#
# Prereqs: the `oc` CLI, logged in (copy the `oc login --token=...` command from
# the web console: user menu -> "Copy login command") and pointed at the target
# project (`oc project <name>`, or `oc new-project sendlock` first time).
# Everything else -- secret generation, image build, database, migrations,
# APP_URL -- is handled here. Safe to re-run; the same command does first
# deploys and redeploys.
#
# NOTE: the in-cluster build clones the git repo (openshift/buildconfig.yaml,
# branch `dev`) -- push your commits before deploying; local-only changes are
# not built.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

function Fail($message) {
    Write-Host "ERROR: $message" -ForegroundColor Red
    exit 1
}

if (-not (Get-Command oc -ErrorAction SilentlyContinue)) {
    Fail "the 'oc' CLI is not installed -- https://mirror.openshift.com/pub/openshift-v4/clients/ocp/stable/"
}
oc whoami | Out-Null
if ($LASTEXITCODE -ne 0) {
    Fail "not logged in -- copy the 'oc login --token=...' command from the OpenShift web console (user menu -> Copy login command)."
}
$namespace = oc project -q
Write-Host "==> Deploying SendLock to project '$namespace'"

# 1. Runtime secret -- generated once with random credentials, never rotated here.
$secret = oc get secret sendlock-secrets --ignore-not-found -o name
if (-not $secret) {
    Write-Host '==> Creating sendlock-secrets'
    $keyBytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($keyBytes)
    $appKey = 'base64:' + [Convert]::ToBase64String($keyBytes)
    $dbPassword = [guid]::NewGuid().ToString('N')
    oc create secret generic sendlock-secrets `
        --from-literal="APP_KEY=$appKey" `
        --from-literal="DB_USERNAME=sendlock" `
        --from-literal="DB_PASSWORD=$dbPassword"
    if ($LASTEXITCODE -ne 0) { Fail 'could not create sendlock-secrets' }
}

# 2. A deployment created before the `component: web` selector fix can't be
#    apply-patched (selectors are immutable) -- recreate it.
$existing = oc get deployment sendlock --ignore-not-found -o name
if ($existing) {
    $component = oc get deployment sendlock -o jsonpath='{.spec.selector.matchLabels.component}'
    if (-not $component) {
        Write-Host '==> Recreating deployment (old immutable selector)'
        oc delete deployment sendlock
        if ($LASTEXITCODE -ne 0) { Fail 'could not delete the outdated deployment' }
    }
}

# 3. Manifests: build config, config, DB, app, route, scheduler.
oc apply -k openshift/
if ($LASTEXITCODE -ne 0) { Fail 'oc apply -k openshift/ failed' }

# 4. Build the image in-cluster from the pushed git branch.
Write-Host '==> Building image (clones the git repo, branch dev)'
oc start-build sendlock --follow
if ($LASTEXITCODE -ne 0) { Fail 'image build failed -- see the build log above' }

# 5. Database up, then migrate + seed roles/permissions (idempotent).
oc rollout status deployment/sendlock-postgresql --timeout=300s
if ($LASTEXITCODE -ne 0) { Fail 'PostgreSQL did not become ready' }
oc delete job sendlock-migrate --ignore-not-found
oc apply -f openshift/migrate-job.yaml
if ($LASTEXITCODE -ne 0) { Fail 'could not create the migrate job' }
Write-Host '==> Running migrations'
oc wait --for=condition=complete job/sendlock-migrate --timeout=300s
if ($LASTEXITCODE -ne 0) {
    oc logs job/sendlock-migrate
    Fail 'migration job did not complete -- logs above'
}

# 6. Point APP_URL at the real route host (`oc apply -k` resets it each run,
#    so re-patch every time, before the rollout below re-caches config).
$routeHost = oc get route sendlock -o jsonpath='{.spec.host}'
oc patch configmap sendlock-config --type merge -p ('{"data":{"APP_URL":"https://' + $routeHost + '"}}')
if ($LASTEXITCODE -ne 0) { Fail 'could not patch APP_URL into sendlock-config' }

# 7. Roll the app onto the new image + config; revive idled/scaled-down pods.
$replicas = oc get deployment sendlock -o jsonpath='{.spec.replicas}'
if ($replicas -eq '0') { oc scale deployment/sendlock --replicas=1 }
oc rollout restart deployment/sendlock
oc rollout status deployment/sendlock --timeout=300s
if ($LASTEXITCODE -ne 0) { Fail 'app rollout did not complete -- check: oc logs deployment/sendlock' }

Write-Host "==> SendLock is live at https://$routeHost"
