# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

SendLock is a Laravel 13 (PHP 8.3) multi-tenant SaaS being built into an email security /
anti-phishing & anti-BEC platform. Its differentiator vs. Microsoft Defender / Proofpoint /
Abnormal is **outbound** protection: recipient verification (SMS/WhatsApp/email) + approval
workflows before sensitive information is sent. Each customer is an **Organization** (the
tenant); users, departments, email scans, trusted/blocked domains, and audit logs all belong
to an organization. Frontend is Blade + Alpine.js + Tailwind (Vite). Auth scaffolding is
Laravel Breeze. Roles/permissions are Spatie Permission.

The build is phased (see the roadmap the team is following):
- **Phase 1 (done):** org hierarchy (head/sub-orgs), worker numbers, expanded roles +
  security middleware, role-aware sidebar navigation, Abnormal-inspired UI shell.
- **Phase 2 (done):** Trust Center (trusted/blocked domains, verified recipients, vendor
  bank accounts) + consolidated risk-scoring engine (domain/content/financial).
- **Phase 3 (done):** "Send Protected" outbound flow → recipient verification
  (SMS/WhatsApp/email, stubbed) → multi-step approval workflow (the differentiator).
- **Phase 4 (done):** inbound gateway signals — SPF/DKIM/DMARC, URL inspection, attachment
  analysis, and platform threat intelligence, all composed into `RiskEngine`.

All four phases are implemented. External integrations (SMS/WhatsApp/threat feeds/email auth)
are built behind driver interfaces with fake/log/null drivers, configured in
`config/sendlock.php` (`SENDLOCK_VERIFICATION_DRIVER` default `log`;
`SENDLOCK_EMAIL_AUTH_DRIVER` default `null`) — swap in real providers later.

## Commands

```bash
composer dev          # run server + queue + pail logs + vite together (primary dev loop)
composer test         # clears config then runs the full Pest suite
composer setup        # install deps, copy .env, key:generate, migrate, npm build

php artisan test                              # run all tests
php artisan test --filter=ProfileTest         # single test class
php artisan test tests/Feature/ProfileTest.php # single test file
php artisan migrate                           # apply migrations
php artisan db:seed --class=RolesAndPermissionsSeeder  # seed roles/permissions (REQUIRED before app is usable)
vendor/bin/pint       # format PHP (Laravel Pint)
npm run dev / npm run build  # vite assets
```

## Environments differ between dev and test

- **Dev/local** uses **PostgreSQL** (`DB_CONNECTION=pgsql`, database `sendlock`) — see `.env`.
- **Tests** run on **SQLite `:memory:`** (configured in `phpunit.xml`), so the DB resets per run.
- Tests use **Pest** (`pestphp/pest`), not plain PHPUnit. Write tests in Pest style.

## Architecture / conventions you must follow

### Organization hierarchy
`organizations.parent_id` is self-referencing: a **head** organization (`type = 'head'`,
`parent_id = null`) owns **sub** organizations (`type = 'sub'`). `Organization` exposes
`parent()`, `children()`, `isHead()`, `isSub()`, and `descendantIds()` (self + children, for
scoping a head admin across its tree). New sign-ups create a head org; `SubOrganizationController`
(behind `headorg.admin`) lets a head admin manage its sub-orgs, scoped to its own children.

### Worker numbers
`users.worker_number` is the human-facing staff id, **entered manually** and validated **unique
per organization** (`Rule::unique(...)->where(organization_id)`), distinct from the auto
`users.id`. It is set in `UserManagementController` create/edit. Self-registration leaves it
null for the founding admin (no manual input at signup) — set later via User Management.

### Multi-tenancy is enforced manually, not by a global scope
There is no tenant middleware or Eloquent global scope. **Every query in a controller must
be scoped by `organization_id`** using `auth()->user()->organization_id`. The established
pattern for route-model-bound records is to re-query with the tenant filter rather than
trust the bound model:

```php
$user = User::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($user->id);
```

Omitting this scope is a cross-tenant data leak. `UserManagementController` is the
reference implementation. When adding any new tenant-owned resource, replicate this pattern.

### Roles and access control
- Roles are seeded in `RolesAndPermissionsSeeder`: **Super Admin, Head Organization Admin,
  Organization Admin, Manager, Employee, Security Officer, Auditor**. Permissions are
  assigned there too — edit that seeder to change the permission matrix. Tests that hit
  role-dependent flows must `$this->seed(RolesAndPermissionsSeeder::class)` (RefreshDatabase
  does not seed). `tests/Pest.php` exposes a global `makeUser($org, $role)` helper that
  creates an active user in an org and assigns a role — use it instead of hand-rolling
  `User::factory()` + `assignRole()` in feature tests.
- **Super Admin** is platform-wide (no org scoping); the dashboard and several controllers
  branch on `auth()->user()->hasRole('Super Admin')`. `User` exposes `isSuperAdmin()`,
  `isHeadOrgAdmin()`, `isOrgAdmin()` — prefer these helpers over raw `hasRole()` strings.
- Middleware aliases (registered in `bootstrap/app.php`): `superadmin` (`EnsureSuperAdmin`),
  `headorg.admin` (`EnsureHeadOrgAdmin` — super + head admin), `org.admin` (`EnsureOrgAdmin`
  — super + head + org admin).

### Navigation & UI shell
The layout is a role-aware left sidebar (`layouts/navigation.blade.php`) + top bar
(`layouts/app.blade.php`), styled after abnormal.com (slate sidebar, violet accent). Menu
sections render conditionally on the role helpers above. Use the `<x-sidebar-link>` component
(takes `:active` and an `:icon` SVG-path string). Not-yet-built menu items route to named
**placeholder** routes (defined in `routes/web.php`) that render `placeholder.blade.php` — so
every menu is navigable. When you build a real feature, replace its placeholder route.
- Super Admin accounts are protected: they cannot be deactivated/deleted/managed by org
  admins, and only a Super Admin may assign the Super Admin role.
- New org sign-up (`Auth/RegisteredUserController`) creates the Organization **and** its
  first user, then assigns `Organization Admin`. Registration is org-creation, not just user-creation.

### Audit logging
Mutating actions are recorded via `App\Helpers\AuditLogger::log($action, $entityType, $entityId, $description)`.
It auto-captures `organization_id`, `user_id`, and IP. Call it after create/update/
activate/deactivate/delete on tenant resources (see `UserManagementController`).

### Risk engine (the scoring pipeline)
`App\Services\RiskEngine::evaluate(['sender_email'=>…, 'subject'=>…, 'email_content'=>…], $orgId)`
is the single entry point. It composes three signal services (all in `app/services/`, namespace
`App\Services`) and returns `['domain','risk_score','risk_level','decision','findings','signals']`:
- `DomainIntelligenceService` — blocklist (instant 100), trust-list, and a battery of
  offline impersonation heuristics: algorithmic typosquatting (confusable-character
  de-obfuscation + Levenshtein edit-distance against a built-in brand list), homograph/IDN
  (punycode + non-ASCII), lookalike-of-trusted-vendor, brand-as-subdomain abuse, disposable
  domains, high-risk TLDs, and random-looking (DGA) labels. A domain **not** in the Trust
  Center is treated as high risk on its own (+70 → HIGH/RECIPIENT_VERIFY before any other
  signal); a **trusted** domain returns early with score 0 and no flags. It emits structured
  `domain_flags` (untrusted/typosquat/lookalike/homograph/subdomain_abuse/disposable/
  suspicious_tld/entropy) that `FlaggedDomainService` records to `flagged_domains` so a repeat
  use can be warned on — new types must be added to `FlaggedDomainService::SEVERITY`.
- `ContentIntelligenceService` — fraud-intent phrase weights (bank change, wire transfer,
  urgency…), capped at 65.
- `ContentClassifier` (AI deep pass, `app/services/Ai/`) — runs after the rule-based content
  service; `NullContentClassifier` (default, no-op) and `GeminiContentClassifier` (Google
  free-tier Gemini, beta) implement the same contract — Claude is the Tier 3 production swap.
  Driver bound in `AppServiceProvider` from `sendlock.ai.driver`; resolved in `RiskEngine` via
  `app(ContentClassifier::class)`. Capped at 50, degrades to no signal on any error/missing key/
  empty content. Tests use `Http::fake`.
- `FinancialDataService` — extracts IBAN/SWIFT/account numbers and compares them to the org's
  `VendorBankAccount` baseline; a mismatch (+60) is the strongest BEC signal.

- `EmailAuthenticationService` — SPF/DKIM/DMARC; only explicit *failures* add score (unknown
  = 0). Driver via `config('sendlock.email_auth.driver')` (default `null` = all unknown); a
  scan may pass explicit `auth` results that win over the driver.
- `HeaderIntelligenceService` — inbound BEC header tells (no network): Reply-To mismatch (+25),
  Return-Path mismatch (+15), display-name impersonating a different address (+30), capped 50.
  Reads the optional `headers` key (`from_name`/`reply_to`/`return_path`) on the scan; absent
  headers add 0. Wired into the email-scan form, not the outbound Send Protected flow.
- `UrlInspectionService` — extracts links from content; flags anchor/href domain mismatch,
  raw-IP links, URL shorteners, high-risk TLDs (capped 50).
- `AttachmentAnalysisService` — filename-level: dangerous executables, macro Office docs,
  archives, double-extension disguises (capped 50). Scan form takes one filename per line.
  The scan also accepts one **uploaded file**: `OcrService` (driver bound in `AppServiceProvider`
  from `sendlock.ocr.driver`, default `null` = no-op) extracts its text and folds it into the
  content the engines analyse, and its filename joins the attachment checks. Enable real OCR with
  `SENDLOCK_OCR_DRIVER=tesseract` + the Tesseract binary on PATH (`TesseractOcrDriver` degrades to
  empty on any failure). Tests bind a fake `OcrDriver` in the container, so the suite stays offline.
- `ThreatIntelligenceService` — the curated platform `ThreatIntelDomain` list (severity → score,
  managed by Super Admin via `ThreatIntelController`, `superadmin`-gated, global/not tenant-scoped)
  is authoritative and checked first. For domains not on it, the configured external feeds
  (`app/services/ThreatFeeds/`: `GoogleSafeBrowsingFeed`, `VirusTotalFeed` behind the `ThreatFeed`
  interface) are consulted and verdicts cached in `threat_intel_cache` (`ThreatIntelCache`, global)
  for `sendlock.threat_feeds.cache_ttl` minutes. Feeds are opt-in via `SENDLOCK_THREAT_FEEDS`
  (comma-separated keys) **and** a per-feed API key; with none enabled (default) no external calls
  are made. A feed failure degrades to no score. Tests use `Http::fake`.

Score → level/decision: ≥90 CRITICAL/QUARANTINE, ≥70 HIGH/RECIPIENT_VERIFY, ≥30
MEDIUM/MANAGER_APPROVAL, ≥10 LOW/ALLOW, else SAFE/ALLOW. Every signal service is additive and
only raises score on a positive detection, so a trusted/benign email stays SAFE. The verdict
also includes `confidence` (0–100, higher when more independent signal services corroborate)
and decision-derived `recommendations` (operator next-steps); both are persisted on `email_scans`
and `approval_requests`. `EmailScanController::analyze`
calls the engine and persists an `EmailScan` (no inline scoring — keep it that way; add new
signals as services composed by `RiskEngine`). `SecurityInsightsController` powers the read-only
Threat Overview and Blocked Attempts pages from scan history (Super Admin sees all orgs).

### Trust Center
`TrustCenterController` (routes under `trust-center`, gated by `org.admin`) manages four
tenant-scoped lists in one tabbed view: `TrustedDomain`, `BlockedDomain`, `VerifiedRecipient`,
`VendorBankAccount`. Domains are normalized (lowercased, stripped of scheme/path/email) before
storage; destroy actions check the record's `organization_id` against the current tenant.

### Send Protected → verification → approval (the workflow)
`ProtectedEmailController` (`/send-protected`) scores an outbound email with `RiskEngine`
(the recipient is the counterparty) and hands the verdict to `App\Services\ApprovalWorkflow`,
which creates an `ApprovalRequest` and runs the state machine:
- decision → status: `ALLOW`→`RELEASED`, `MANAGER_APPROVAL`→`PENDING_APPROVAL`,
  `RECIPIENT_VERIFY`→`PENDING_VERIFICATION` (then approval), `QUARANTINE`→`BLOCKED`.
- A recipient already on the `VerifiedRecipient` list skips verification.
- `markRecipientVerified()` / `approve()` / `reject()` advance via `nextStatus()` (clears the
  satisfied requirement, recomputes); approvals are logged to `ApprovalAction`.

`RecipientVerificationController` issues/checks codes via `VerificationService`, which resolves
a `VerificationChannel` per channel type: SMS/WhatsApp use the configured driver, email + the
default `log` driver use the stub (codes are 6-digit, TTL from config). The `twilio` driver
(`TwilioVerificationChannel`) sends real SMS/WhatsApp via Twilio's REST API (Laravel `Http`, no
SDK) and **degrades to the log stub if credentials are missing**, so nothing is sent/billed
until `SENDLOCK_VERIFICATION_DRIVER=twilio` + `TWILIO_*` are set in `.env`. `ApprovalController`
(`/approvals`) is role-gated to Manager and above. All three controllers re-check
`organization_id` on every bound model. Status badges render via `<x-status-badge :status="…" />`.
Tests fake the HTTP client (`Http::fake`) so the Twilio path is covered without real calls.

### Non-standard directory casing
This project uses lowercase `app/services/` and `app/helpers/` directories, but the PHP
namespaces are still PSR-4 capitalized (`App\Services`, `App\Helpers`). Keep that mismatch
when adding files there, or PSR-4 autoloading will fail.

