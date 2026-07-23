#!/usr/bin/env bash
# One-command SendLock deploy to the *current* OpenShift project:
#
#   ./openshift/deploy.sh
#
# Prereqs: the `oc` CLI, logged in (copy the `oc login --token=…` command from
# the web console: user menu → "Copy login command") and pointed at the target
# project (`oc project <name>`, or `oc new-project sendlock` first time).
# Everything else — secret generation, image build, database, migrations,
# APP_URL — is handled here. Safe to re-run; the same command does first
# deploys and redeploys.
#
# NOTE: the in-cluster build clones the git repo (openshift/buildconfig.yaml,
# branch `dev`) — push your commits before deploying; local-only changes are
# not built.
set -euo pipefail
cd "$(dirname "$0")/.."

command -v oc >/dev/null 2>&1 || {
    echo "ERROR: the 'oc' CLI is not installed — https://mirror.openshift.com/pub/openshift-v4/clients/ocp/stable/" >&2
    exit 1
}
oc whoami >/dev/null || {
    echo "ERROR: not logged in — copy the 'oc login --token=…' command from the OpenShift web console (user menu → Copy login command)." >&2
    exit 1
}
NAMESPACE=$(oc project -q)
echo "==> Deploying SendLock to project '${NAMESPACE}'"

# 1. Runtime secret — generated once with random credentials, never rotated here.
if ! oc get secret sendlock-secrets >/dev/null 2>&1; then
    echo "==> Creating sendlock-secrets"
    oc create secret generic sendlock-secrets \
        --from-literal=APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
        --from-literal=DB_USERNAME=sendlock \
        --from-literal=DB_PASSWORD="$(head -c 64 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32)"
fi

# 2. A deployment created before the `component: web` selector fix can't be
#    apply-patched (selectors are immutable) — recreate it.
if oc get deployment sendlock >/dev/null 2>&1; then
    if [[ -z "$(oc get deployment sendlock -o jsonpath='{.spec.selector.matchLabels.component}')" ]]; then
        echo "==> Recreating deployment (old immutable selector)"
        oc delete deployment sendlock
    fi
fi

# 3. Manifests: build config, config, DB, app, route, scheduler.
oc apply -k openshift/

# 4. Build the image in-cluster from the pushed git branch.
echo "==> Building image (clones the git repo, branch dev)"
oc start-build sendlock --follow

# 5. Database up, then migrate + seed roles/permissions (idempotent).
oc rollout status deployment/sendlock-postgresql --timeout=300s
oc delete job sendlock-migrate --ignore-not-found
oc apply -f openshift/migrate-job.yaml
echo "==> Running migrations"
if ! oc wait --for=condition=complete job/sendlock-migrate --timeout=300s; then
    echo "ERROR: migration job did not complete — logs:" >&2
    oc logs job/sendlock-migrate || true
    exit 1
fi

# 6. Point APP_URL at the real route host (`oc apply -k` resets it each run,
#    so re-patch every time, before the rollout below re-caches config).
ROUTE_HOST=$(oc get route sendlock -o jsonpath='{.spec.host}')
oc patch configmap sendlock-config --type merge -p "{\"data\":{\"APP_URL\":\"https://${ROUTE_HOST}\"}}"

# 7. Roll the app onto the new image + config; revive idled/scaled-down pods.
if [[ "$(oc get deployment sendlock -o jsonpath='{.spec.replicas}')" == "0" ]]; then
    oc scale deployment/sendlock --replicas=1
fi
oc rollout restart deployment/sendlock
oc rollout status deployment/sendlock --timeout=300s

echo "==> SendLock is live at https://${ROUTE_HOST}"
