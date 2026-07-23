# Deploying SendLock to OpenShift

Everything needed to run SendLock on OpenShift lives in this directory plus the
`Containerfile` / `docker/start.sh` / `.dockerignore` at the repo root.

## Architecture at a glance

| Piece | File | Notes |
| --- | --- | --- |
| Deploy script | `deploy.sh` / `deploy.ps1` | **The** way to deploy — one idempotent command that does everything below |
| App image | `../Containerfile` | Multi-stage: Node 22 builds Vite assets → Composer installs vendor → UBI PHP + Apache serves `public/` on **8080**, non-root, arbitrary-UID safe |
| Runtime bootstrap | `../docker/start.sh` | Caches config/routes/views against the injected env on every pod start |
| In-cluster build | `buildconfig.yaml` | ImageStream (local lookup policy, so manifests stay namespace-agnostic) + Docker-strategy BuildConfig from this git repo (`dev` branch) |
| Config | `configmap.yaml` | Non-secret env: prod flags, `stderr` logging, DB pointers, driver defaults |
| Secrets | `secret.example.yaml` | Template only — create the real `sendlock-secrets` manually |
| App | `deployment.yaml` + `service.yaml` + `route.yaml` | Probes on Laravel's `/up`; edge-TLS Route with insecure→HTTPS redirect |
| Database | `postgresql.yaml` | Single-replica PostgreSQL 16 (SCLorg image) + 5Gi PVC; swap for an operator/managed DB for production HA |
| Migrations + seed | `migrate-job.yaml` | `migrate --force` + `RolesAndPermissionsSeeder` (idempotent); run per deploy |
| Scheduler | `scheduler-cronjob.yaml` | Hourly `schedule:run` (covers the hourly threat-feed import) |

The app is stateless: sessions, cache, and queue are database-backed, and the
only file upload (OCR) is read from a temp file and discarded — so no PVC is
needed for the app pods and `replicas` can be scaled freely. No queue worker is
deployed because no queued jobs exist yet; if jobs are added later, deploy a
second Deployment running `php artisan queue:work`.

## Deploying — step by step (works on any machine)

Nothing in this directory needs editing: the manifests are namespace-agnostic
(pods reference the bare `sendlock:latest`, resolved by the ImageStream's
local lookup policy) and the deploy script generates or derives everything
else (secrets, image, `APP_URL`). The full flow is: **get the code → install
`oc` → log in → pick a project → run the script.**

### Step 1 — Get the code

```bash
git clone https://github.com/che-brenda/sendlock.git   # first time
cd sendlock
git checkout dev
git pull                                               # every time after
```

> **Important:** the in-cluster build clones this git repo from GitHub
> (`buildconfig.yaml`, branch `dev`) — it does **not** upload your local
> checkout. If you have local commits, `git push` them before deploying;
> uncommitted or unpushed changes will not be in the deployed image.

### Step 2 — Install the `oc` CLI (once per machine)

`oc` is OpenShift's command-line client. Check whether you already have it
with `oc version --client`. If not:

- **Windows:** `scoop install openshift-okd-client` — or download
  `openshift-client-windows.zip` from
  <https://mirror.openshift.com/pub/openshift-v4/clients/ocp/stable/>,
  unzip it, and put `oc.exe` somewhere on your `PATH`.
- **macOS:** `brew install openshift-cli`
- **Linux:** download `openshift-client-linux.tar.gz` from the mirror above,
  then `tar xzf` it and move `oc` to `/usr/local/bin`.

### Step 3 — Log in to the cluster (once per session)

Tokens expire (on the Developer Sandbox after ~24h), so repeat this when
`oc whoami` starts failing:

1. Open the OpenShift **web console** in your browser
   (Developer Sandbox: <https://console.redhat.com/openshift/sandbox> →
   *Get started* → *Launch* OpenShift).
2. Click your **username in the top-right corner** → **Copy login command**.
3. Re-authenticate if asked, then click **Display Token**.
4. Copy the whole `oc login --token=sha256~… --server=https://api.…:6443`
   line and run it in your terminal. It should reply `Logged into …`.

### Step 4 — Select the project (namespace)

```bash
oc project            # shows which project you're currently on
```

- **Developer Sandbox:** you get one fixed project named `<username>-dev`;
  `oc login` drops you into it automatically — nothing to do.
- **Your own cluster, first deploy:** `oc new-project sendlock`
- **Your own cluster, existing install:** `oc project sendlock`

The script deploys into whatever project is current, so double-check this
before running it.

### Step 5 — Run the deploy script

From the repo root:

```bash
./openshift/deploy.sh        # Linux / macOS / Git Bash
```

```powershell
.\openshift\deploy.ps1       # Windows PowerShell
```

The same command does first deploys **and** redeploys — it is idempotent and
safe to re-run at any time. In order it:

1. verifies you're logged in and prints the target project;
2. creates the `sendlock-secrets` Secret with a random `APP_KEY` and database
   password — **first run only**, never rotated after that;
3. applies every manifest in this directory (`oc apply -k openshift/`);
4. builds the container image in-cluster from the pushed `dev` branch and
   streams the build log (the slowest step — several minutes);
5. waits for PostgreSQL, then runs the migrate + roles/permissions seed Job;
6. points `APP_URL` at the Route host the cluster actually assigned;
7. restarts the app, waits for a healthy rollout, and prints the live URL:
   `==> SendLock is live at https://sendlock-<project>.apps.…`

If any step fails the script stops there with the relevant log — fix the
cause and just run it again.

### Step 6 — Verify

Open the printed URL: the SendLock landing page should load over HTTPS.
Register an organization to confirm the database is wired up (sign-up →
billing page is the expected flow). Useful checks if something looks off:

```bash
oc get pods                  # web pod Running/Ready? postgresql Running?
oc get builds                # latest build Complete?
oc logs deployment/sendlock  # Laravel/Apache output
```

### Redeploying after changes

```bash
git push origin dev          # the build pulls from GitHub, not your disk
./openshift/deploy.sh        # or .\openshift\deploy.ps1
```

## Environment / drivers

All external integrations default to free/log/null drivers (nothing is sent or
billed): recipient verification logs codes, AI classification is off, threat
feeds are off, OCR is off, and **mail is the `log` driver — password resets are
not actually delivered**. Enable each by adding its key to `sendlock-secrets`
and flipping the driver in `configmap.yaml` (see `.env.production.example` for
the full annotated list). Note OCR additionally requires baking the Tesseract
binary into the image.

## Known production gaps (deliberate, pre-launch)

- **Payments are stubbed** — checkout always records `paid`; no real gateway.
- Mail driver is `log` (see above).
- Subscription expiry is surfaced in the UI but not enforced by any job.

## Troubleshooting

- **"Application is not available" (the router's 503 page)** — no ready app pod
  behind the Route. Re-running `deploy.sh`/`deploy.ps1` fixes every common
  cause (image never built, migrations never ran, pods idled/scaled to zero —
  the Developer Sandbox idles workloads after inactivity and stops them
  nightly). To diagnose instead: `oc get pods` (is a `sendlock-…` web pod
  Running/Ready?), `oc get builds` (did a build complete?),
  `oc logs deployment/sendlock`.
- **Pod CrashLoops on start** — `oc logs deploy/sendlock`; `docker/start.sh`
  fails fast if `config:cache` can't run (usually a missing/typo'd env var).
- **Redirects/assets load over http://** — `TRUSTED_PROXIES` unset or `APP_URL`
  wrong; both live in the ConfigMap, restart after changing.
- **403 on odd-looking requests** — the application firewall
  (`app/Http/Middleware/Firewall.php`) blocks attack-signature URLs; see the
  Security Center or `security_events` table.
- **Login/permission errors on a fresh DB** — the migrate Job (which also seeds
  roles/permissions) hasn't completed.
