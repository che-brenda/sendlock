# SendLock — Manual Demonstration Guide

A hands-on script to demonstrate **every** subsystem: UI, database, the "API"/integration
layer, the security model, the backend log system, the risk engine, the outbound workflow, and
the test suite. Follow it top-to-bottom for a full demo, or jump to a section.

> Conventions: **UI** steps say what to click/enter; **DB** steps use `php artisan tinker` or
> `psql`; **Logs** steps read `storage/logs/laravel.log`. Expected results are in _italics_.

---

## 0. Prerequisites & one-time setup

| Step | Command | Expected |
|---|---|---|
| Install deps | `composer install` && `npm install` | — |
| Env | copy `.env.example` → `.env`, set Postgres `DB_*`, then `php artisan key:generate` | — |
| Migrate | `php artisan migrate` | _all tables created_ |
| **Seed roles (required)** | `php artisan db:seed --class=RolesAndPermissionsSeeder` | _7 roles + permissions_ |
| Build assets | `npm run build` (or `npm run dev`) | — |
| Run the app | `composer dev` (server + queue + logs + vite) | _serves http://127.0.0.1:8000_ |

> ⚠️ Without the role seed the app is unusable. OCR is already wired (Tesseract). External
> providers (AI, Twilio, threat feeds) stay dormant on their null defaults until you add keys
> in §8.

---

## 1. Bootstrap accounts (no users are seeded)

Registration creates an **Organization + its first Organization Admin**. There is no pre-seeded
Super Admin — you promote one manually.

| # | Action | Where | Expected |
|---|---|---|---|
| 1 | Register "Acme Corp" | `/register` (UI) | _new head org + you as **Organization Admin**_ |
| 2 | Promote yourself to Super Admin (demo only) | tinker (below) | _platform-wide access_ |
| 3 | Create staff users with roles | **Users** sidebar → Create | _Manager / Employee / etc._ |

**Promote a Super Admin (DB):**
```bash
php artisan tinker
>>> $u = App\Models\User::where('email','you@acme.test')->first();
>>> $u->assignRole('Super Admin'); $u->save();
```
Log out/in. _The sidebar now shows Super-Admin-only sections (Organizations, Threat Intelligence)._

**Roles you can assign** (Users → Create/Edit): Organization Admin, Manager, Employee, Security
Officer, Auditor (and Head Organization Admin for org trees). _Only a Super Admin may grant Super
Admin; Super Admin accounts can't be deactivated by org admins._

---

## 2. UI tour (role-aware shell)

The layout is a slate left sidebar + top bar (abnormal.com-styled). Menu sections render by role.

| Menu item | Route | Who sees it | Purpose |
|---|---|---|---|
| Dashboard | `/dashboard` | all | KPIs (orgs/users/depts) + last 10 audit logs |
| Email Security Scan | `/email-scans` | all | Inbound analysis (the risk engine) |
| Send Protected | `/send-protected` | all | Outbound differentiator |
| Recipient Verification | `/recipient-verification` | all | Issue/check codes |
| Approvals | `/approvals` | Manager+ | Approve/reject queue |
| Trust Center | `/trust-center` | Org Admin+ | Trusted/Blocked/Recipients/Vendor banks |
| Flagged Domains | `/flagged-domains` | all | Auto-recorded impersonation register |
| Threat Overview / Blocked Attempts | `/threat-overview`, `/blocked-attempts` | all | Read-only insights from scan history |
| Users / Departments | `/users`, `/departments` | Org Admin+ | Tenant management |
| Sub-Organizations | `/sub-organizations` | Head Org Admin | Manage org tree |
| Organizations | `/organizations` | **Super Admin** | All tenants |
| Threat Intelligence | `/threat-intel` | **Super Admin** | Global blocklist |
| Audit Logs | `/audit-logs` | all (scoped) | Activity trail |
| Billing / Policies / Reports | placeholders | all | Planned-feature pages (navigable) |

_Tip: every menu is navigable — unbuilt items render a styled "planned" page, so nothing 404s._

---

## 3. Risk Engine demo — Email Security Scan (inbound)

Go to **Email Security Scan** (`/email-scans`). Each scan composes domain + content + header +
URL + financial + attachment + threat + (optional) AI signals into **score 0–100 → SAFE / LOW /
MEDIUM / HIGH / CRITICAL** with a decision, **confidence %**, **recommended action**, and findings.

Run these scenarios in order:

| # | Inputs | Expected verdict |
|---|---|---|
| A. **Trusted + benign** | First add `partner.com` in Trust Center (§5). Sender `hello@partner.com`, subject "Lunch", body "free to catch up?" | _SAFE / ALLOW, high confidence_ |
| B. **Unknown sender** | Sender `hi@some-new-vendor.com`, benign body | _≈70 → HIGH / RECIPIENT_VERIFY_ ("Domain not found in Trust Center") |
| C. **BEC bank-change** | Sender `billing@unknown-vendor.com`, subject "Change of bank account", body "update our bank account for the next wire transfer" | _HIGH/CRITICAL_; findings list fraud phrases |
| D. **Typosquat / homograph** | Sender `accounts@paypa1.com` (one is a digit) | _flagged "resembles brand paypal (typosquat)"_ |
| E. **Header spoofing** | Open **Message headers**; sender `hello@partner.com`, Reply-To `attacker@evil.com` | _score rises_; finding "Reply-To domain … differs" |
| F. **Blocked domain** | First block `fraud.com` in Trust Center; sender `x@fraud.com` | _100 / CRITICAL / QUARANTINE_ (short-circuits) |
| G. **Financial mismatch** | Add Vendor Bank Account: domain `supplier.com`, account `123456789`. Scan sender `ac@supplier.com`, body "use our new bank account 987654321" | _+60 mismatch finding_ |
| H. **OCR attachment** | Attach an **image/scan** containing fraud text under "Attachment file" | _finding "Text extracted via OCR…"_, content signals fire (Tesseract live) |
| I. **Repeat-domain warning** | Scan an untrusted domain (e.g. `bad-vendor.com`) **twice** | _2nd scan shows a popup warning + escalate-to-manager option_ |

**Verify each scan persisted (DB):**
```bash
php artisan tinker
>>> App\Models\EmailScan::latest()->first(['sender_domain','risk_score','risk_level','decision','confidence']);
>>> App\Models\EmailScan::latest()->first()->findings;          // array
>>> App\Models\FlaggedDomain::get(['domain','detection_type','times_seen']); // repeat tracking
```
_Scenarios feed **Threat Overview** and **Blocked Attempts** (`/threat-overview`, `/blocked-attempts`)._

---

## 4. The differentiator — Send Protected → Verify → Approve

| # | Action | Where | Expected |
|---|---|---|---|
| 1 | Compose an outbound email to a **new external** recipient (`vendor@partner-new.com`), subject "Invoice" | `/send-protected` | _scored as recipient counterparty_ |
| 2 | Submit | — | _verdict routes the request: ALLOW→released, MANAGER_APPROVAL→pending approval, **RECIPIENT_VERIFY→pending verification**, QUARANTINE→blocked_ |
| 3 | Go to **Recipient Verification**, pick the request, send a code via **email** | `/recipient-verification` | _code issued (log driver writes it to the log — see §7)_ |
| 4 | Read the 6-digit code from the log, enter it | — | _recipient verified → advances to **PENDING_APPROVAL**_ |
| 5 | As a **Manager**, open **Approvals**, Approve | `/approvals` | _status → RELEASED; an `ApprovalAction` row is written_ |
| 6 | (Alt) Reject | — | _status → REJECTED_ |

**DB trace:**
```bash
>>> App\Models\ApprovalRequest::latest()->first(['recipient_email','decision','status','requires_verification','requires_approval']);
>>> App\Models\RecipientVerification::latest()->first(['channel','code','status']);
>>> App\Models\ApprovalAction::latest()->first(['action','user_id','notes']);
```
_A recipient already on the Trust Center **Verified Recipients** list skips step 3–4 automatically._

---

## 5. Trust Center (tenant trust ecosystem)

**Trust Center** (`/trust-center`, Org Admin+) — four tabbed lists, all tenant-scoped & normalized:

| Tab | Demo | Effect on scoring |
|---|---|---|
| Trusted Domains | add `partner.com` | benign in scans (scenario A) |
| Blocked Domains | add `fraud.com` | instant CRITICAL/QUARANTINE (scenario F) |
| Verified Recipients | add `vip@partner.com` | skips verification in Send Protected |
| Vendor Bank Accounts | `supplier.com` / `123456789` | financial-mismatch baseline (scenario G) |

_Show tenant isolation: a second org's lists never appear here (see §6)._

---

## 6. Security system demonstration

| Control | How to demonstrate | Expected |
|---|---|---|
| **Authentication** | Visit `/dashboard` while logged out | _redirect to `/login`_ |
| **RBAC middleware** | As an Employee, visit `/trust-center` (org.admin) or `/threat-intel` (superadmin) | _403 Forbidden_ |
| **Role helpers** | `superadmin`/`headorg.admin`/`org.admin` aliases in `bootstrap/app.php` | gate routes in `routes/web.php` |
| **Tenant isolation** | Create Org B + a user. As Org A admin, try Org B's user/scan/trust records | _not visible / 403 — every query is `organization_id`-scoped_ |
| **Super-Admin protection** | As an Org Admin, try to deactivate a Super Admin (`/users`) | _blocked_ |
| **Plan / feature gate** | Set an org's plan and toggle a paid driver (§8) | _free org never triggers a paid provider_ |
| **Verification codes** | 6-digit, TTL from config, single-use, superseded on re-issue | see `VerificationService` |

**Tenant-isolation check (DB):**
```bash
>>> $a = App\Models\Organization::first(); $b = App\Models\Organization::create(['organization_name'=>'Beta','type'=>'head','status'=>true]);
# Records created under $b->id never surface in $a's scoped controllers.
```

**Plan/feature gate (DB):**
```bash
>>> $o = App\Models\Organization::first();
>>> $o->subscription_plan = 'free'; $o->save();  $o->hasFeature('ai_classification');   // false
>>> $o->subscription_plan = 'pro';  $o->save();  $o->hasFeature('sms_verification');     // true
```
_Plans: `free` (none) · `beta` (AI) · `pro` (AI + SMS/WhatsApp) · `enterprise` (`*` all). Map in
`config/sendlock.php`._

---

## 7. Backend log system

SendLock has **three** log surfaces:

| Surface | Where | Demonstrates |
|---|---|---|
| **Audit trail** | `/audit-logs` (UI) + `audit_logs` table | Mutating actions: scans, sends, verifications, approvals, user changes — auto-captures org/user/IP |
| **Application log** | `storage/logs/laravel.log` (or `composer dev` pail pane) | The **verification-code stub** writes codes here; errors/warnings land here |
| **Queue / scheduler** | `composer dev` queue pane | Threat-list importer (`sendlock:import-threat-feeds`) and queued work |

**Audit trail (UI + DB):**
```bash
>>> App\Models\AuditLog::latest()->take(5)->get(['action','entity_type','description','ip_address','created_at']);
```
_After §3–§4 you'll see `SCAN`, `PROTECTED_SEND`, `VERIFICATION_SENT`, `VERIFICATION_CONFIRMED`,
approval actions, etc._

**Read a verification code from the log (so you can complete §4 without Twilio):**
```bash
# After issuing an email/sms code with the default 'log' driver:
tail -n 40 storage/logs/laravel.log    # look for the verification message + 6-digit code
```

**Run the threat-list importer on demand (writes to log + cache):**
```bash
php artisan sendlock:import-threat-feeds   # no-op unless SENDLOCK_THREAT_LISTS is set (§8)
```

---

## 8. "API" / integration system

> **There is no first-party public REST API yet** (planned — F1 in the roadmap). The system's
> programmatic surfaces are: (a) the **web routes** in `routes/web.php` (session-authenticated),
> and (b) the **outbound integration layer** — external APIs consumed behind driver interfaces,
> each with a safe default so nothing calls out until configured.

| Integration | Driver / interface | Enable with | Demo without spending |
|---|---|---|---|
| **OCR** (Tesseract) | `OcrDriver` → `TesseractOcrDriver` | ✅ already on (`SENDLOCK_OCR_DRIVER=tesseract`) | scenario H |
| **AI — Gemini** (free beta) | `ContentClassifier` → `GeminiContentClassifier` | `SENDLOCK_AI_DRIVER=gemini` + `GEMINI_API_KEY` + plan ≥ beta | unit test fakes HTTP |
| **AI — Claude** (paid) | `ContentClassifier` → `ClaudeContentClassifier` | `SENDLOCK_AI_DRIVER=claude` + `ANTHROPIC_API_KEY` + plan ≥ pro | unit test fakes HTTP |
| **SMS/WhatsApp** (Twilio) | `VerificationChannel` → `TwilioVerificationChannel` | `SENDLOCK_VERIFICATION_DRIVER=twilio` + `TWILIO_*` + plan ≥ pro | log stub when off |
| **Threat feeds** | `ThreatFeed` → Safe Browsing / VirusTotal | `SENDLOCK_THREAT_FEEDS=…` + per-feed key | curated list only |
| **Phishing lists** | `sendlock:import-threat-feeds` | `SENDLOCK_THREAT_LISTS=openphish,phishtank` + scheduler | importer |
| **Email auth** (SPF/DKIM/DMARC) | `EmailAuthenticationService` | `SENDLOCK_EMAIL_AUTH_DRIVER` (default `null`) | scan may pass explicit `auth` |

**Demonstrate a driver swap (no keys, no spend)** — every external path is covered by tests that
fake the HTTP client, so you can show the integration working end-to-end offline:
```bash
php artisan test tests/Feature/ClaudeContentTest.php      # Anthropic Messages API path
php artisan test tests/Feature/AiContentTest.php          # Gemini path
php artisan test tests/Feature/ThreatFeedsTest.php        # Safe Browsing / VirusTotal + cache
php artisan test tests/Feature/TwilioVerificationTest.php # Twilio SMS/WhatsApp (+ plan gate)
```
_Each asserts the exact request shape (headers, body, endpoint) against a faked response — proof
the integration is wired correctly without a live account._

**Inspect all registered routes (the web "API"):**
```bash
php artisan route:list
```

---

## 9. Database — what to show

| Table | Holds | Tenant-scoped? |
|---|---|---|
| `organizations` | tenants (head/sub, `subscription_plan`) | self (hierarchy) |
| `users` | staff (`organization_id`, `worker_number`, status) | yes |
| `departments` | org departments | yes |
| `trusted_domains` / `blocked_domains` | trust lists | yes |
| `verified_recipients` / `vendor_bank_accounts` | trust ecosystem | yes |
| `email_scans` | inbound verdicts (+`decision`,`confidence`,`recommendations`,`findings`) | yes |
| `flagged_domains` | auto-recorded impersonation + `times_seen` | yes |
| `approval_requests` | outbound workflow state | yes |
| `recipient_verifications` | issued codes | yes |
| `approval_actions` | approve/reject audit | yes |
| `audit_logs` | activity trail | yes |
| `threat_intel_domains` | **global** curated blocklist | no (platform) |
| `threat_intel_cache` | **global** external-feed verdict cache | no (platform) |

```bash
php artisan tinker
>>> DB::table('email_scans')->count();
>>> App\Models\Organization::with('users','departments')->first();
# Or raw psql:  psql -d sendlock -c "\dt"   then   SELECT risk_level, count(*) FROM email_scans GROUP BY 1;
```

---

## 10. Automated test suite (prove the whole thing)

```bash
php artisan test              # full suite — 133 tests, runs on in-memory SQLite, fully offline
php artisan test --filter=RiskEngine
vendor/bin/pint --test        # code style check
```
_Covers: risk engine + every signal, header/domain/AI/threat-feed/OCR/verification/approval/plan-gate
flows, and tenant isolation. External providers are exercised via `Http::fake` and null/bound-fake
drivers, so the suite needs no keys and makes no network calls._

---

## 11. Appendix A — role × access matrix

| Area | Super Admin | Head Org Admin | Org Admin | Manager | Employee | Security Officer | Auditor |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Dashboard / Scan / Send | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Approvals | ✅ | ✅ | ✅ | ✅ | — | — | — |
| Trust Center | ✅ | ✅ | ✅ | — | — | — | — |
| Users / Departments | ✅ | ✅ | ✅ | — | — | — | — |
| Sub-Organizations | ✅ | ✅ | — | — | — | — | — |
| Organizations / Threat Intel | ✅ | — | — | — | — | — | — |
| Audit Logs / Reports | ✅ | ✅ | scoped | scoped | — | ✅ | ✅ |

_(Exact permission grants live in `RolesAndPermissionsSeeder`; route gates in `routes/web.php`.)_

## 12. Appendix B — known limitations to mention during a demo

- **Attachments:** filename + image-OCR only — no PDF/Office text or macro extraction yet.
- **Audit logs:** captured but **not tamper-evident** (no hash chain) and coverage is partial.
- **Approvals:** single linear gate — no Finance/Legal/parallel/escalation chains.
- **Recipient intelligence:** verified-list lookup only — no behavioural/history scoring yet.
- **Email auth/DNS:** unknown by default (no live DNS lookups).
- **RBAC:** spec's Finance Officer / Compliance Officer roles not yet seeded.
- **No first-party REST API / SIEM export / mobile add-ins** yet (roadmap F-tier).

Full status: [SPRINT_TIERED_ROADMAP.md](SPRINT_TIERED_ROADMAP.md) · [SPEC_GAP_ANALYSIS.md](SPEC_GAP_ANALYSIS.md).
