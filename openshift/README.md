# Deploying SendLock to OpenShift

Everything needed to run SendLock on OpenShift lives in this directory plus the
`Containerfile` / `docker/start.sh` / `.dockerignore` at the repo root.

## Architecture at a glance

| Piece | File | Notes |
| --- | --- | --- |
| App image | `../Containerfile` | Multi-stage: Node 22 builds Vite assets → Composer installs vendor → UBI9 PHP 8.3 + Apache serves `public/` on **8080**, non-root, arbitrary-UID safe |
| Runtime bootstrap | `../docker/start.sh` | Caches config/routes/views against the injected env on every pod start |
| In-cluster build | `buildconfig.yaml` | ImageStream + Docker-strategy BuildConfig from this git repo (`dev` branch) |
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

## First deployment

```bash
oc new-project sendlock            # or oc project <existing>

# 1. Secrets (never commit real values)
oc create secret generic sendlock-secrets \
  --from-literal=APP_KEY="$(php artisan key:generate --show)" \
  --from-literal=DB_USERNAME=sendlock \
  --from-literal=DB_PASSWORD="$(openssl rand -base64 24)"

# 2. Edit kustomization.yaml: replace SENDLOCK_NAMESPACE with your project name.
#    Optionally edit configmap.yaml (APP_URL, mail sender).

# 3. Apply everything (build config, DB, app, route, scheduler)
oc apply -k openshift/

# 4. Build the image in-cluster
oc start-build sendlock --follow

# 5. Migrate + seed (required before the app is usable)
oc delete job sendlock-migrate --ignore-not-found
oc apply -f openshift/migrate-job.yaml
oc wait --for=condition=complete job/sendlock-migrate --timeout=300s

# 6. Point APP_URL at the real Route host, then restart to re-cache config
oc get route sendlock -o jsonpath='{.spec.host}'
#   → set APP_URL in configmap.yaml to https://<that host>, then:
oc apply -f openshift/configmap.yaml
oc rollout restart deployment/sendlock
```

## Subsequent deploys

```bash
oc start-build sendlock --follow                     # rebuild from the dev branch
oc delete job sendlock-migrate --ignore-not-found
oc apply -f openshift/migrate-job.yaml               # run new migrations
oc rollout restart deployment/sendlock               # pick up the new image
```

(Automate later with a GitHub webhook trigger on the BuildConfig and an
ImageStream trigger annotation on the Deployment.)

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

- **Pod CrashLoops on start** — `oc logs deploy/sendlock`; `docker/start.sh`
  fails fast if `config:cache` can't run (usually a missing/typo'd env var).
- **Redirects/assets load over http://** — `TRUSTED_PROXIES` unset or `APP_URL`
  wrong; both live in the ConfigMap, restart after changing.
- **403 on odd-looking requests** — the application firewall
  (`app/Http/Middleware/Firewall.php`) blocks attack-signature URLs; see the
  Security Center or `security_events` table.
- **Login/permission errors on a fresh DB** — the migrate Job (which also seeds
  roles/permissions) hasn't completed.
