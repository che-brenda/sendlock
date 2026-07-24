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

### Windows / Avast gotcha: `server.php` gets quarantined
On this Windows box, **Avast** (the active real-time AV; Defender is in passive mode) blocks
`vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` as a false-positive
webshell — writes to that exact path return **"Access denied"**. Symptoms: `php artisan serve`
(and therefore `composer dev`) dies with *"Failed opening required '…/resources/server.php'"*.
**Do NOT "fix" this with `composer reinstall`/`composer install`** — Composer removes the whole
`laravel/framework` package first and then aborts on the blocked file, leaving the framework
**entirely uninstalled** (a much worse break). The real fix is an **Avast exception for
`C:\Projects\sendlock`** (Avast → Settings → General → Exceptions), or temporarily pausing Avast
shields; only then does `composer install` restore `server.php`. Tests (`php artisan test`) don't
need `server.php`, so the suite runs fine even while `serve` is broken.

## Architecture / conventions you must follow

### Organization hierarchy
`organizations.parent_id` is self-referencing: a **head** organization (`type = 'head'`,
`parent_id = null`) owns **sub** organizations (`type = 'sub'`). `Organization` exposes
`parent()`, `children()`, `isHead()`, `isSub()`, and `descendantIds()` (self + children, for
scoping a head admin across its tree). New sign-ups create a head org; `SubOrganizationController`
(behind `headorg.admin`) lets any admin of a head org (Organization Admin or Head Organization
Admin, via `User::canManageSubOrganizations()`) manage its sub-orgs, scoped to its own children. The
**dashboard** (`DashboardController`) aggregates totals + recent activity across `descendantIds()`,
so a head org sees everything beneath it; it renders a **Sub-Organizations** section (each sub-org's
user/dept/scan counts), and Head Org Admins get a **read-only drill-down** (`sub-organizations.show`)
into a sub-org's members, scans, and activity. Super Admin's dashboard lists all head orgs with their
sub-org counts. The hierarchy is **two levels** (head → sub); sub-orgs don't nest further.

### Worker numbers
`users.worker_number` is the human-facing staff id, **entered manually** and validated **unique
per organization** (`Rule::unique(...)->where(organization_id)`), distinct from the auto
`users.id`. It is set in `UserManagementController` create/edit. Self-registration leaves it
null for the founding admin (no manual input at signup) — set later via User Management.

### First-sign-in password reset (admin-created users)
When an org admin creates a user (`UserManagementController::store`), the admin **does not** set a
password — the system issues a strong temporary one (`Str::password`), hashes it as the login
password, and also stores the plaintext on `users.temporary_password` (an **`encrypted` cast**, so
it's ciphertext at rest) with `users.must_change_password = true`. The admin reads that credential off
the Users list / detail page via `<x-temporary-password :user="…" />` (only while
`User::hasPendingTemporaryPassword()` is true). The `EnsurePasswordChanged` middleware is **appended
to the `web` group** (`bootstrap/app.php`), so it guards *every* page: an authenticated user with
`must_change_password` is bounced to the branded first-sign-in screen (`password.first-change`,
`Auth\ForcePasswordChangeController`) — only that route + `logout` are exempt. Setting a new password
clears both flags (`must_change_password = false`, `temporary_password = null`), so the credential
vanishes from the admin view. Self-registered founding admins default to `must_change_password = false`
(no forced reset). `temporary_password` is in the model's `#[Hidden]` list so it never leaks via
serialization.

### Registration is org-centric
`Auth\RegisteredUserController` registers an **organization**, not a person — it collects org name,
industry, work email, **phone (country dial-code `<select>` + local number, combined server-side)**,
password, and a required **Terms & Conditions** acceptance (`terms` → `accepted`; the public `/terms`
page is `Route::view`, route name `terms`). Dial codes come from `config('countries.php')` (`list`
of `iso`/`name`/`dial`, plus a `default`); validation pins `country_code` to that list. The founding
admin account has **no personal name** — its `name` is set to the organization name and
`first_name`/`last_name` stay null. Because of that, render any user via the `User::display_name` and
`User::initials` accessors (they fall back from first/last → `name` → email), never raw
`first_name . last_name` — the org account would otherwise show blank. Admin-created *workers* (via
`UserManagementController`) **do** collect first/last name; only the self-registered org account is
nameless-personally.

### Billing & the subscription gate
Registration creates the org with `subscription_status = 'pending'` and redirects to **`billing.index`**
(not the dashboard). The `EnsureSubscribed` middleware (appended to the `web` group right after
`EnsurePasswordChanged`) holds any user whose org `subscriptionPending()` at the billing page — only
`billing.*` (incl. `billing.free`), the first-sign-in password routes, and `logout` are exempt; Super
Admins (no org) and pre-existing orgs (null status) pass through. `BillingController` (`/billing`)
renders the packages from `config('sendlock.billing')` (`free`→`free`, `starter`→`beta`,
`professional`→`pro`, `enterprise`→`enterprise` — the `plan` key links to the entitlement map, so
**paying for a package is what grants its features**). A package priced at `0` (the **Free** tier) is
activated with no charge: the card posts to `billing.free` (`selectFree()`), which sets the plan active
and creates **no** `Payment` (and `checkout`/`process` redirect a free package back rather than showing
a $0 checkout). Paid checkout takes one of three **stubbed** payment methods (`visa` card / `mtn_momo`
MTN Mobile Money / `paypal`), validated per-method via `required_if`. `process()` records a `Payment`
(always `paid` — no real gateway is called), sets the org's `subscription_plan` +
`subscription_status = 'active'` + `subscribed_at` + `subscription_expires_at` (one month out; Free =
null), logs `SUBSCRIPTION_PAID`, and redirects to the dashboard (gate lifted). To add a package or
method, edit `config/sendlock.php` `billing` — no code change needed.

Two read views sit on top of this: **`subscription.index`** (`/subscription`, `org.admin`, org sidebar
→ *Subscription*) shows the org's own plan, dates, expiry and payment history; **`billing.customers`**
(`/billing/customers`, `superadmin`, the product owner's *Customer Billing* sidebar link) lists every
organization's plan/status/dates/last-payment plus platform revenue totals.

Billing **status is expiry-aware**, not just the `subscription_status` column: `Organization::subscriptionState()`
returns `pending | free | active | expiring_soon | expired | none` (a paid plan past its
`subscription_expires_at` reads `expired` even while the column says `active`; `expiring_soon` =
within `EXPIRING_SOON_DAYS` = 7). Render it everywhere via `<x-subscription-badge :organization="…" />`
(used on the subscription page, the dashboard status banner shown to org admins, and the customer
overview). Expiry is **surfaced, not enforced** — there's no job that re-gates an expired org; the
subscription page and dashboard banner just show a *Renew* CTA (→ `billing.index`).

### Application firewall (WAF) & Security Center
`App\Http\Middleware\Firewall` is **prepended to the `web` group** (`bootstrap/app.php`), so it runs
first on every browser request. It (1) inspects the **request line (path + query) and User-Agent**
against attack signatures in `config/firewall.php` (`malicious_patterns`: path-traversal, XSS, SQLi,
LFI/RFI, code-exec; `blocked_agents`: sqlmap/nikto/…; oversized-URI) and, on a match, records a
`SecurityEvent` and `abort(403)`; and (2) **hardens every response** with security headers (CSP,
X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, + HSTS over HTTPS). **It deliberately
never scans the request BODY** — this app analyses malicious-looking email content submitted in the
body, so bodies must not be firewalled (there's a test locking this in). Toggle via `FIREWALL_ENABLED`.
`SecurityController` (`/security`, `security.index`, sidebar → *Security Center*) is the user-facing
assurance page: it lists the active protections by layer and the firewall's blocked-attack tally;
Super Admins also see recent blocked requests from `security_events`.

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
  `headorg.admin` (`EnsureHeadOrgAdmin` — **sub-org powers**: super, or any Org/Head-Org Admin
  whose org is a head org — see `User::canManageSubOrganizations()`), `org.admin` (`EnsureOrgAdmin`
  — super + head + org admin).
- **Plans & feature entitlements (the paid-feature gate):** `organizations.subscription_plan`
  (default `Free`) maps to a feature list via `config('sendlock.plans')`; `Organization::hasFeature($f)`
  is the gate that keeps paid providers from firing for non-entitled tenants (e.g.
  `ai_classification` for beta+, `sms_verification` for pro+). Check it before any billable call —
  `RiskEngine` already gates the AI classifier this way. Plans: `free` (none), `beta`
  (ai_classification), `pro` (+ sms/whatsapp verification), `enterprise` (`*` = all).

### Navigation & UI shell
The layout is a role-aware left sidebar (`layouts/navigation.blade.php`) + top bar
(`layouts/app.blade.php`), styled after abnormal.com (slate sidebar, violet accent).
**Flash notifications are global**: `<x-flash />` (in both `layouts/app` and `layouts/guest`)
renders `session('success'|'error'|'warning'|'info')`, Breeze `session('status')`, and validation
errors as dismissible auto-fading toasts (top-right, Alpine). So any controller `->with('success', …)`
/ `->withErrors(…)` automatically pops a toast — **don't add per-page success/error banners**
(they'd double up); in-form field-level validation errors still render inline on create/edit forms.
**Consequential actions must confirm** — wrap a form's submit in `<x-confirm-submit label="…"
message="Are you sure…?" confirm="Yes" class="…" />` (a reusable inline Alpine two-step: button →
"message · No · Yes"; only Yes submits). Used on Send / Send anyway / escalate / approve / reject; the
older `onsubmit="return confirm(…)"` on cancel/trust/delete/deactivate is equivalent. Menu
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

**Visibility is role-tiered** via the `AuditLog::scopeVisibleTo($user)` query scope (the single
source of truth, used by both `AuditLogController` and the dashboard's recent-activity feed):
Super Admin sees every org's logs; an Org/Head-Org Admin sees their whole org tree
(`descendantIds()`); everyone else (Manager, Employee, Security Officer, Auditor) sees **only the
logs of actions they performed**. Use `->visibleTo(auth()->user())` rather than re-deriving the rule.

### Risk engine (the scoring pipeline)
`App\Services\RiskEngine::evaluate(['sender_email'=>…, 'subject'=>…, 'email_content'=>…], $orgId)`
is the single entry point. It composes the signal services below (all in `app/services/`, namespace
`App\Services`) and returns `['domain','risk_score','risk_level','decision','findings','signals']`:
- `DomainIntelligenceService` — blocklist (instant 100), trust-list, and a battery of
  offline impersonation heuristics: algorithmic typosquatting (confusable-character
  de-obfuscation + Levenshtein edit-distance against a built-in brand list), homograph/IDN
  (punycode + non-ASCII), lookalike-of-trusted-vendor, brand-as-subdomain abuse, disposable
  domains, high-risk TLDs, and random-looking (DGA) labels. A domain **not** in the Trust
  Center is treated as high risk on its own (+70 → HIGH/RECIPIENT_VERIFY before any other
  signal); a **trusted** domain returns early with score 0 and no flags. **Public email providers**
  (`PublicEmailProviders` — gmail/outlook/yahoo/…) are **recognized** as valid providers (that check
  passes) but recognition is **not** domain-level trust — the engine does not grant them trust even if a
  `trusted_domains` row exists (that would trust every attacker on gmail); it proceeds to check the
  exact address, and only a specific `VerifiedRecipient` at them is trusted. This applies at scoring
  **and** in `ApprovalWorkflow::isTrustedCounterparty`, and the Trust Center / trust-whole-domain action
  refuse to add a public provider. No check is skipped — the `analysis` breakdown always records an
  **Email Provider** row (recognized/private) and an **In Trust List** row (verified contact / trusted
  domain / look-alike / not-found→verification-required) alongside every other signal. It emits structured
  `domain_flags` (untrusted/typosquat/lookalike/homograph/subdomain_abuse/disposable/
  suspicious_tld/entropy) that `FlaggedDomainService` records to `flagged_domains` so a repeat
  use can be warned on — new types must be added to `FlaggedDomainService::SEVERITY`.
- `EmailIntelligenceService` — **full email-address trust, distinct from domain trust.** Domain
  trust says "anyone @vendor.com is fine"; this verifies the WHOLE sender address against the org's
  `VerifiedRecipient` list. **Exact match wins first** → **trusted** (overrides the untrusted-domain
  +70, so a verified contact on a consumer domain like gmail.com still reads trusted; and two
  genuinely-distinct verified contacts like `jone@` and `joan@` each resolve to themselves, never
  flagged against each other). A non-exact address within a one/two-edit or confusable-swap of a
  verified contact — in the **local part OR the domain** — → **impersonation** (+75,
  `contact_impersonation`/`suggested_contact` signals, never trusted, even on a trusted domain), and
  the verdict's `analysis.suggestion` carries the **closest** verified address as a *"Address not found
  — did you mean X?"* prompt (rendered as an amber banner on the risk page). This stops a spoofed
  `chebrendn93@gmail.com` reading as trusted after `chebrenda93@gmail.com` was verified. Entering a full
  email in the Trust Center's trusted-**domain** field stores it as a `VerifiedRecipient`
  (address-level), not the bare domain — otherwise all of `gmail.com` would trust.
- `ContentIntelligenceService` — fraud-intent phrase weights (bank change, wire transfer,
  urgency…), capped at 65.
- `ContentClassifier` (AI deep pass, `app/services/Ai/`) — runs after the rule-based content
  service; `NullContentClassifier` (default, no-op), `GeminiContentClassifier` (Google free-tier
  Gemini, beta), and `ClaudeContentClassifier` (Anthropic Messages API, raw HTTP + strict-JSON
  `output_config.format`, paid/production) all implement the same contract — promoting beta→prod
  is a driver swap (`SENDLOCK_AI_DRIVER`). Driver bound in `AppServiceProvider` from
  `sendlock.ai.driver`. **The AI call only fires when the tenant's plan entitles it** — `RiskEngine`
  checks `Organization::hasFeature('ai_classification')` (only when a non-null driver is configured),
  so a free/beta org never triggers a paid provider. Capped at 50, degrades to no signal on any
  error/missing key/empty content/refusal. Tests use `Http::fake`. Claude defaults to
  `claude-opus-4-8` (override via `ANTHROPIC_MODEL`).
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
  The scan also accepts one **uploaded file**: `OcrService` (`app/services/Ocr/`, driver bound in
  `AppServiceProvider` from `sendlock.ocr.driver`, default `null` = no-op, behind the `OcrDriver`
  interface alongside `NullOcrDriver`/`TesseractOcrDriver`) extracts its text and folds it into the
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
  are made. A feed failure degrades to no score. Tests use `Http::fake`. Bulk phishing lists
  (OpenPhish/PhishTank) are imported into the same cache by the `sendlock:import-threat-feeds`
  command (scheduled hourly in `routes/console.php`, no-op unless `SENDLOCK_THREAT_LISTS` is set).
- `MxIntelligenceService` — MX-record presence via a `Dns\DnsResolver` driver (`LiveDnsResolver`
  = built-in `checkdnsrr`, default in dev/prod; `NullDnsResolver` = unknown, pinned in tests via
  `SENDLOCK_DNS_DRIVER=null`). Missing MX +10; unknown = 0.
- `DomainAgeService` — newly-registered-domain signal via a `DomainAge\DomainAgeResolver` driver
  (`NullDomainAgeResolver` default = unknown/inert; `RdapDomainAgeResolver` = free keyless RDAP over
  `Http`, enabled with `SENDLOCK_DOMAIN_AGE_DRIVER=rdap`). ≤30 days old +25; unknown = 0.
- `SensitiveDataService` — PII/DLP detection (Luhn-checked card numbers, IBAN, SWIFT, gov-ID, and
  confidentiality markers). **Score-neutral** (detection only, so it never distorts the threat
  verdict) — emits findings + a `sensitive_data` signal list surfaced on the analysis page.
- `CommunicationHistoryService` (Communication Relationship / Recipient Intelligence) — records each
  scanned counterpart in `communications` (aggregated per org+address) **after** scoring, so a message
  never counts as its own history. `forEmail`/`forDomain` power the "Previous Communication" row and
  the "Similar Trusted Domain" panel (a lookalike's genuine vendor domain + how often you've mailed it).

Score → level/decision: ≥90 CRITICAL/QUARANTINE, ≥70 HIGH/RECIPIENT_VERIFY, ≥30
MEDIUM/MANAGER_APPROVAL, ≥10 LOW/ALLOW, else SAFE/ALLOW. Every signal service is additive and
only raises score on a positive detection, so a trusted/benign email stays SAFE. The verdict
also includes `confidence` (0–100, higher when more independent signal services corroborate)
and decision-derived `recommendations` (operator next-steps); both are persisted on `email_scans`
and `approval_requests`. `EmailScanController::analyze`
calls the engine and persists an `EmailScan` (no inline scoring — keep it that way; add new
signals as services composed by `RiskEngine`). `SecurityInsightsController` powers the read-only
Threat Overview and Blocked Attempts pages from scan history (Super Admin sees all orgs).

The verdict also carries a structured **`analysis`** breakdown (built by `RiskEngine::buildAnalysis`):
one labelled row per check — Domain Age, Similarity, MX, SPF, DMARC, Domain Reputation (derived),
Previous Communication (+ an additive Sensitive-Data row) — each with an `ok|warn|bad|unknown` status,
plus a `similar_trusted` panel. It's persisted on `email_scans.analysis` (json). After a scan,
`EmailScanController::analyze` **redirects to `email-scans.show`** — the **Domain Risk Analysis page**
(gauge + signal checklist + similar-trusted panel) — so the full analysis appears automatically;
recent-scan rows on the index link to it. To add a row, extend `buildAnalysis`.

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
- **Trust gate (no auto-release for the unregistered):** `ALLOW`→`RELEASED` only when the counterparty
  is a **registered trusted** domain/address — `ApprovalWorkflow::isTrustedCounterparty` (a
  `VerifiedRecipient` exact address *or* an active `TrustedDomain`). Any other (untrusted) counterparty
  is forced to `requires_approval` so it can never slip out on an `ALLOW` verdict; it must clear a human
  step — recipient verification and/or manager approval. **Nothing auto-sends:** `RELEASED` means
  *cleared to send*, not sent — the show page then shows a green **Safe to send** card (recipient found
  in trust list / identity verified / manager-approved / risk level) and the user must press **Send
  email** (`protected-email.send`, sets `sent_at`; only a `RELEASED` request can be sent). The
  `protected-email.show` page surfaces the pending path too: for an untrusted pending send it presents
  four options — **Verify recipient** (→ Recipient Verification Center, SMS/WhatsApp/email),
  **Request manager authorization** (`escalate`/`escalateToApproval` — drops verification, still needs a
  manager), **View risk analysis** (→ `email-scans.show` of the `EmailScan` that `store()` now persists
  and links via `email_scan_id`), and **Cancel** (`protected-email.cancel` → `CANCELLED`). **After a send
  clears BOTH recipient verification AND manager approval** (`ApprovalRequest::wasVerifiedAndApproved()`),
  the page **asks the user to confirm** adding the counterparty to the trusted database —
  `protected-email.trust` takes a `scope` of `address` (→ `VerifiedRecipient`) or `domain` (→
  `TrustedDomain`), each behind a JS confirm. It is refused (422) unless verified **and** approved, so
  trust is never granted off a bare auto-release or an escalation-only release.
- A recipient already on the `VerifiedRecipient` list skips verification.
- `markRecipientVerified()` / `approve()` / `reject()` advance via `nextStatus()` (clears the
  satisfied requirement, recomputes); approvals are logged to `ApprovalAction`.

`RecipientVerificationController` issues/checks codes via `VerificationService` (the
`app/services/Verification/` driver family), which resolves a `VerificationChannel` per channel
type: SMS/WhatsApp use the configured driver, email + the default `log` driver use the
`LogVerificationChannel` stub (codes are 6-digit, TTL from config). The `twilio` driver
(`TwilioVerificationChannel`) sends real SMS/WhatsApp via Twilio's REST API (Laravel `Http`, no
SDK) and **degrades to the log stub if credentials are missing**, so nothing is sent/billed
until `SENDLOCK_VERIFICATION_DRIVER=twilio` + `TWILIO_*` are set in `.env`. `VerificationService`
also **plan-gates the paid channel**: SMS/WhatsApp only use the Twilio channel when the org's plan
entitles `sms_verification`/`whatsapp_verification` (see the plan gate), else it falls back to the
log stub — so a free/beta org never triggers a billable send. `ApprovalController`
(`/approvals`) is role-gated to Manager and above. All three controllers re-check
`organization_id` on every bound model. Status badges render via `<x-status-badge :status="…" />`.
Tests fake the HTTP client (`Http::fake`) so the Twilio path is covered without real calls.

### Controller map
Most controllers are described in their feature sections above; this is the full surface so
nothing is missed (all tenant-scoped via `organization_id` unless noted):
- `DashboardController` — role-aware dashboard aggregated across `descendantIds()` (see Org hierarchy).
  Also feeds the **Top Risk Reasons** donut (`<x-risk-chart>`) with real per-org data from
  `RiskReasonStats::forOrganizations($ids)` — which tallies each risky `EmailScan`'s single primary
  reason (from its persisted `analysis` rows) into a colour-coded breakdown. The same component with no
  `:segments` runs a demo-cycling animation on the public landing page.
- `UserManagementController` — users `resource` + `activate`/`deactivate`; the **reference** for
  tenant scoping, worker numbers, and audit logging.
- `DepartmentController` — departments `resource` (standard tenant-scoped CRUD).
- `EmailScanController` — inbound scan form + `analyze` (persists an `EmailScan` from `RiskEngine`).
- `FlaggedDomainController` — review register for auto-flagged domains (`flagged_domains`), plus
  `requestApproval`: the "request manager authorization" escalation from the flagged-domain warning,
  which routes through `ApprovalWorkflow` and **forces ≥ manager sign-off** (an `ALLOW` is bumped to
  `MANAGER_APPROVAL`, never auto-released). The `<x-flagged-domain-warning>` popup (repeat-use of a
  flagged domain, `send` context) offers **Cancel / Request manager authorization / View risk analysis /
  Verify recipient / Send anyway** — the latter three re-submit to `protected-email.store` with an
  `intent` (`analysis`→`email-scans.show`, `verify`→Recipient Verification Center, else the show page).
- `AuditLogController` — read-only `index` over `AuditLog`, role-scoped via `scopeVisibleTo` (see Audit logging).
- `BillingController` — pricing (`billing.index`), free activation (`billing.free`), stubbed checkout
  (`billing.checkout`/`billing.process`), the org's own `subscription` page, and the Super Admin
  `customers` overview; see Billing & the subscription gate.
- `SecurityInsightsController` — read-only Threat Overview + Blocked Attempts (Super Admin sees all orgs).
- `SecurityController` — Security Center (`/security`, `security.index`); the WAF assurance page; see the WAF section.
- `TrustCenterController` — Trust Center (`org.admin`); see its section.
- `ProtectedEmailController` / `RecipientVerificationController` / `ApprovalController` — the
  Send Protected → verification → approval workflow; see its section.
- `SubOrganizationController` — sub-org management + read-only drill-down (`headorg.admin`); see Org hierarchy.
- `OrganizationManagementController` — **Super Admin** org CRUD (`Route::resource('organizations')`,
  `superadmin`-gated, **not** tenant-scoped). Note: `OrganizationController` is an **empty stub** — do
  not use it; the live controller is `OrganizationManagementController`.
- `ThreatIntelController` — Super Admin platform threat-intel list (`superadmin`, global); see Risk engine.
- `ProfileController` + `Auth/*` — Laravel Breeze scaffolding (profile + auth). Mostly standard, but
  `Auth\RegisteredUserController` (org-centric sign-up → billing) and `Auth\ForcePasswordChangeController`
  (first-sign-in reset) carry custom logic; see their sections.

### Non-standard directory casing
This project uses lowercase `app/services/` and `app/helpers/` directories, but the PHP
namespaces are still PSR-4 capitalized (`App\Services`, `App\Helpers`). Keep that mismatch
when adding files there, or PSR-4 autoloading will fail.

## Deployment (OpenShift / S2I)

Deploys to **OpenShift** via **Source-to-Image (S2I)** on the sclorg **`php:8.3-ubi10`** builder
(Apache + php-fpm). Live target: Red Hat Developer Sandbox, project `chebrenda93-dev`, BuildConfig/
Deployment/Route all named `sendlock`, tracking the `deployment-openshift` branch. Everything the
image doesn't do for a Laravel app is bridged by three files in `.s2i/` — understand all three
together, because getting the app to actually serve required fixing **three independent failures**,
each of which alone produced a blank/500/welcome page:

1. **DocumentRoot → the Red Hat welcome page.** The image serves Apache from `/opt/app-root/src`,
   but Laravel's front controller is in `public/`, so `/` hits no index and Apache shows the *"Test
   Page for the HTTP Server on Red Hat Enterprise Linux."* The image builds its DocumentRoot from a
   **`DOCUMENTROOT`** env var (`DocumentRoot "/opt/app-root/src${DOCUMENTROOT}"`), so
   **`.s2i/environment` sets `DOCUMENTROOT=/public`**. (Earlier attempts set `DOCUMENT_ROOT` /
   `OPENVIP_DOCUMENT_ROOT` — wrong names, silently ignored.)
2. **php-fpm strips the environment → `MissingAppKeyException` (500).** `www.conf` ships
   `;clear_env = no` commented out, so fpm defaults to `clear_env = on` and the PHP app sees **none**
   of the Deployment's env vars (APP_KEY, DB_*, …) — even though PID 1 has them. Fix: **`.s2i/bin/run`
   materializes a `.env` file** from the container env on every start (Laravel reads the file from
   disk regardless of fpm's stripping). Only `APP_KEY` has no safe default and must be supplied via
   `oc set env` / a Secret.
3. **Vite assets are never built → `Vite manifest not found` (500 on every view).** Every Blade view
   uses `@vite()`; the PHP builder runs `composer install` but not `npm`. The builder *does* ship
   Node/npm, so **`.s2i/bin/assemble` runs `npm ci && npm run build`** (plus the standard assemble,
   `chmod -R g+rwX storage bootstrap/cache database` for the arbitrary non-root UID, `storage:link`,
   and — for the zero-infra **SQLite** DB — creates the file, `migrate --force`, and seeds
   `RolesAndPermissionsSeeder`). Migrations/seed run **at build time**; the SQLite file is baked into
   the image, so **data resets on every rebuild** — fine for the sandbox demo, swap to pgsql + a DB
   service for anything persistent.

**Both `.s2i/bin/*` scripts must be executable** (mode `100755` in git — set with
`git update-index --chmod=+x`, since `core.filemode=false` on Windows won't pick it up). A
non-executable S2I script is silently ignored and the image's default is used instead — which is
exactly how the old `.s2i/action_hooks/` approach failed: **OpenShift v4 S2I never runs
`action_hooks/`** (that's an OpenShift v2 concept), so the DocumentRoot/welcome-page logic that used
to live there never executed. That directory and the old root-level `.htaccess` fallback have been
removed; `DOCUMENTROOT` is the single mechanism now. `public/.htaccess` remains Laravel's standard
front-controller rewrite.

Deploy loop: push to `deployment-openshift` → `oc start-build sendlock` (or `oc start-build sendlock
--from-dir=. --follow` to build local, uncommitted state) → `oc rollout status deployment/sendlock`
→ curl the route. Required one-time app env (not in git): `oc set env deployment/sendlock APP_KEY=…
APP_URL=https://sendlock-chebrenda93-dev.apps.rm2.thpm.p1.openshiftapps.com APP_ENV=production
APP_DEBUG=false`. Because `--no-dev` strips `spatie/laravel-ignition`, a 500 shows only a generic
"Server Error" page even with `APP_DEBUG=true` — get the real exception from `oc logs
deployment/sendlock` (`LOG_CHANNEL=stderr`) or by rendering via `php artisan tinker` in the pod.

**Two runtime traps that only surface as a login/register 500 (static pages still render):**
1. **A stray `DB_DATABASE` breaks SQLite.** `config/database.php` resolves the sqlite file from
   `env('DB_DATABASE', database_path('database.sqlite'))`, so any `DB_DATABASE` set on the Deployment
   (e.g. a leftover MySQL-style `DB_DATABASE=sendlock` from a DB template) makes Laravel open a bogus
   relative file → *"Database file at path [sendlock] does not exist"* on every query, while DB-free
   pages (landing, `/login` GET) look fine. `.s2i/bin/run` now **forces the absolute baked-in path
   whenever `DB_CONNECTION=sqlite` and `DB_DATABASE` isn't already absolute**, so the sqlite demo is
   self-correcting; still, don't set MySQL placeholders on the Deployment (`oc set env deployment/sendlock
   DB_HOST- DB_PORT- DB_DATABASE- DB_USERNAME- DB_PASSWORD-` to clear them). `oc set env … --list`
   should show only `DB_CONNECTION=sqlite`.
2. **The Route needs edge TLS.** `APP_URL` is `https://…` but the sandbox route is created without TLS,
   so browsers on the https link get a router **503** while `http://` works — looks like the app is
   down. Fix once: `oc patch route sendlock --type=merge -p '{"spec":{"tls":{"termination":"edge",
   "insecureEdgeTerminationPolicy":"Redirect"}}}'` (edge cert is the router's wildcard; http→https
   redirects). Deployment `env` and Route changes are cluster objects — they **persist across S2I
   rebuilds** (a rebuild only swaps the image), so these are one-time fixes, not per-deploy.

