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

## Deploying — any machine, one command

Nothing in this directory needs editing: manifests are namespace-agnostic
(pods reference the bare `sendlock:latest`, resolved by the ImageStream's
local lookup policy) and the deploy script generates/derives everything else.
So on any PC: clone/pull, log in, run the script.

```bash
# 1. Log in: web console → user menu → "Copy login command" → paste it
oc login --token=sha256~… --server=https://api.…:6443

# 2. Pick the project (Developer Sandbox: your fixed <user>-dev project)
oc project <name>          # or first time on your own cluster: oc new-project sendlock

# 3. Deploy (same command for first deploy and every redeploy)
./openshift/deploy.sh      # Windows: .\openshift\deploy.ps1
```

The script is idempotent. Each run it: generates `sendlock-secrets` (random
`APP_KEY`/`DB_PASSWORD`) if missing → applies `oc apply -k openshift/` →
builds the image in-cluster → waits for PostgreSQL → runs the migrate/seed
Job → patches `APP_URL` to the actual Route host → restarts the app and waits
for a healthy rollout, printing the live URL.

**The build clones the git repo** (`buildconfig.yaml`, branch `dev`) — push
your commits before deploying; local-only changes are not built.

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
