# SendLock

SendLock is a multi-tenant email-security SaaS (anti-phishing / anti-BEC) built on Laravel.
Its differentiator is **outbound** protection: recipient verification (SMS/WhatsApp/email)
and approval workflows before sensitive information leaves the organization, alongside a
full inbound risk-scoring engine (domain intelligence, content analysis, SPF/DKIM/DMARC,
URL & attachment inspection, threat feeds).

Each customer is an **Organization** (the tenant). See `CLAUDE.md` for the full
architecture guide and conventions.

## Getting started

### Requirements

- **PHP 8.4+** (the locked dependencies require ≥ 8.4.1) with extensions:
  `pdo_sqlite` (or `pdo_pgsql` for PostgreSQL), `mbstring`, `openssl`, `curl`,
  `fileinfo`, `zip`
- **Composer 2**
- **Node.js 20+** and npm

### Setup

```bash
git clone https://github.com/che-brenda/sendlock.git
cd sendlock

composer setup        # install deps, copy .env, generate key, migrate, build assets
php artisan db:seed --class=RolesAndPermissionsSeeder   # REQUIRED before the app is usable

composer dev          # server + queue + logs + vite together
# or simply: php artisan serve
```

The app is now at <http://localhost:8000>. Register a new organization at `/register` —
sign-up creates the org and its founding admin, then lands on the billing page (pick the
**Free** plan to pass the subscription gate and reach the dashboard).

The default `.env` uses **SQLite** and stubbed drivers (verification codes are written to
the log, no external services are called, nothing is billed). For PostgreSQL, set the
`DB_*` variables accordingly. All integrations (Twilio, AI classification, threat feeds,
OCR) are opt-in via `.env` — see the annotated `.env.example`.

### Tests

```bash
composer test         # full Pest suite (SQLite in-memory, no external calls)
php artisan test --filter=ProfileTest   # single class
```

### Container

A production image is defined in `Containerfile` (multi-stage; UBI PHP + Apache,
non-root, port 8080):

```bash
podman build -f Containerfile -t sendlock .
```

CI (`.github/workflows/container-build.yml`) builds this image and smoke-tests it against
PostgreSQL on every push to `dev`/`main`.

## Deployment (Red Hat OpenShift)

Deploying is one command on any machine: log in with `oc`, then run
`./openshift/deploy.sh` (Windows: `.\openshift\deploy.ps1`) — it builds the image
in-cluster, sets up PostgreSQL, secrets, migrations, and the Route, and prints the live
URL. See **`openshift/README.md`** for the detailed step-by-step guide (installing `oc`,
getting a login token, verifying, troubleshooting).

## Branch workflow

- **`main`** — source of truth.
- **`dev`** — staging; changes land here first and are merged to `main` via pull request
  after review.
