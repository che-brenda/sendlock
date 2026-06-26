# SendLock — Implementation Sprint Backlog

Turns [SPEC_GAP_ANALYSIS.md](SPEC_GAP_ANALYSIS.md) into ordered, buildable tickets. Tickets are
grouped into sprints by value ÷ effort and dependency order. Each ticket states **what to build**,
**what it needs to function** (config / DB / external services), **files** (respecting this repo's
conventions), and **acceptance criteria** (Pest tests).

### Conventions every ticket must follow
- New signal logic = a service in `app/services/` (namespace `App\Services`) returning
  `['score' => int, 'findings' => string[]]`, **composed by `RiskEngine`** — never inline scoring.
- Every tenant query scoped by `auth()->user()->organization_id`; re-query route-bound models
  with the tenant filter (see `UserManagementController`).
- Mutations logged via `App\Helpers\AuditLogger::log(...)`.
- External integrations go behind a **driver interface** with a fake/log/null default, configured
  in `config/sendlock.php`. Nothing bills until real creds are set.
- Tests: Pest, seed `RolesAndPermissionsSeeder`, use the global `makeUser($org, $role)` helper,
  `Http::fake()` for outbound HTTP.
- New menu items reuse `<x-sidebar-link>`; replace placeholder routes with real ones.

---

# SPRINT A — Cheap, high-signal wins (no external deps)

## Ticket A1 — Email / Header Intelligence Engine (§9)
**Goal:** catch the most common BEC tells that need no network calls.

**Build**
- New `app/services/HeaderIntelligenceService.php` → `analyze(array $headers, string $senderDomain): array`.
- Detections (each adds score + finding):
  - **Display-name spoofing**: `From` display name contains a brand/exec name or an email-like
    string whose domain ≠ actual `From` domain. (+30)
  - **Reply-To mismatch**: `Reply-To` domain ≠ `From` domain. (+25)
  - **Return-Path mismatch**: `Return-Path`/envelope-from domain ≠ `From` domain. (+15)
  - **Display-name-is-an-address**: display name itself is a different email address. (+20)
  - Cap total at 50.
- Compose into `RiskEngine::evaluate()` alongside the other services; accept an optional
  `headers` key in the `$email` array (default `[]` → score 0, like the auth service).

**Needs to function**
- `ProtectedEmailController` / `EmailScanController` scan forms gain optional fields:
  `from_name`, `reply_to`, `return_path` (textarea or inputs). No external service.

**Files**: `app/services/HeaderIntelligenceService.php` (new); `app/services/RiskEngine.php`
(compose); scan Blade views (add fields); `EmailScanController`/`ProtectedEmailController` (pass `headers`).

**Acceptance** (`tests/Feature/HeaderIntelligenceTest.php`)
- Reply-To on a different domain raises score & emits finding.
- Display name "CEO <ceo@evil.com>" while From is `staff@company.com` flags spoofing.
- All-aligned headers add 0. Benign email stays LOW end-to-end through `RiskEngine`.

**Depends on:** none. **Est:** S.

---

## Ticket A2 — Risk payload completeness: confidence, recommendations, Safe band (§15)
**Goal:** make every verdict carry confidence + actionable recommendation, per spec §15.

**Build**
- Extend `RiskEngine::result()` payload with:
  - `confidence` (0–100): derive from number/strength of contributing signals (e.g. more independent
    positive signals → higher confidence; single weak signal → lower).
  - `recommendations` (string[]): mapped from decision (e.g. QUARANTINE → "Do not release; report to
    Security"; RECIPIENT_VERIFY → "Verify recipient via SMS before release").
- Add a **Safe** band: split `< 30` into `Safe` (`== 0` or `< 10`) and `Low`. Update the level/decision
  `match` and the spec-aligned label set: Safe / Low / Medium / High / Critical.

**Needs to function**
- Persisted columns on `email_scans` and `approval_requests`: `confidence` (int), `recommendations`
  (json). Migration + model `$casts`. Update views that render the verdict to show confidence +
  recommendation list.

**Files**: `app/services/RiskEngine.php`; migration `..._add_confidence_to_email_scans...`;
`EmailScan`, `ApprovalRequest` models; result Blade partials; `<x-status-badge>` (add Safe).

**Acceptance** (`tests/Feature/RiskEngineTest.php` additions)
- Verdict includes `confidence` and non-empty `recommendations`.
- Score 0 → level `Safe`; existing band tests still pass.

**Depends on:** none (do after A1 so confidence accounts for header signals). **Est:** S.

---

## Ticket A3 — Audit coverage + immutability (§16)
**Goal:** log *every* security event and make logs tamper-evident.

**Build**
- **Coverage:** add `AuditLogger::log(...)` calls to the currently-unlogged mutations:
  - Auth: login, logout, failed login (in `AuthenticatedSessionController`).
  - Approvals: approve / reject / recipient-verified (`ApprovalWorkflow` / `ApprovalController`).
  - Verification: code issued / verified / failed (`RecipientVerificationController`).
  - Trust Center: create/destroy of trusted/blocked domains, vendors, recipients.
  - Threat-intel & policy changes; role changes.
- **Immutability:** append-only enforcement — add a `hash` + `previous_hash` chain on `audit_logs`
  (each row hashes its payload + prior row's hash). Block updates/deletes via a model `booted()`
  guard (`updating`/`deleting` → throw). Add an artisan `audit:verify` command that walks the chain.

**Needs to function**
- Migration adding `hash`, `previous_hash` to `audit_logs`. `AuditLogger` computes the chain on write
  (serialize within a DB transaction / lock to avoid races).

**Files**: `app/helpers/AuditLogger.php`; `AuditLog` model (immutability guard, casts); migration;
call sites listed above; `app/Console/Commands/AuditVerify.php` (new).

**Acceptance** (`tests/Feature/AuditTrailTest.php`)
- Approve/reject/verify/login each write an audit row.
- Updating or deleting an `AuditLog` throws.
- `audit:verify` passes on a clean chain and fails if a row is tampered (test by raw DB update).

**Depends on:** none. **Est:** M.

---

# SPRINT B — Domain & recipient depth

## Ticket B1 — Algorithmic domain analysis (§7, offline checks)
**Goal:** replace the 7-token typosquat list with real algorithms; add the no-network domain checks.

**Build** (extend `DomainIntelligenceService`)
- **Homograph / IDN**: detect mixed-script / punycode (`xn--`) domains and confusable-character
  substitution (Cyrillic 'а' for Latin 'a', `0`/`o`, `1`/`l`). (+40)
- **General edit-distance typosquat**: compare against trusted vendors *and* a built-in brand list
  using Levenshtein + keyboard-adjacency, not just the hard-coded tokens. (+50)
- **Entropy analysis**: high Shannon entropy in the label (random-looking) → suspicious. (+15)
- **Disposable / suspicious TLD at domain level**: bring the URL service's TLD list to bear on the
  sender domain; add a disposable-domain list. (+15)
- **Subdomain abuse**: brand name appearing as a subdomain of an unrelated registrable domain
  (e.g. `paypal.secure-login.com`). (+30)

**Needs to function**
- A `confusables` map + `disposable_domains` + `brands` reference data (config or a small seeded
  table). Pure PHP; no network.

**Files**: `app/services/DomainIntelligenceService.php`; reference data file (`config/sendlock.php`
or `database/seeders/...`); update `domain_flags` types.

**Acceptance** (`tests/Feature/DomainIntelligenceTest.php`)
- `paypa1.com` and Cyrillic-`раypal.com` both flag; `microsoft.com` (trusted) stays benign.
- Punycode/IDN, high-entropy, and subdomain-abuse cases each flag with the right `domain_flags` type.

**Depends on:** none. **Est:** M.

---

## Ticket B2 — DNS / WHOIS resolver driver (§7, network checks)
**Goal:** domain age, MX/SPF presence, reverse-DNS — behind a driver so tests stay offline.

**Build**
- `app/services/Dns/DomainResolver.php` interface + `NullDomainResolver` (default, returns unknown)
  and `LiveDomainResolver` (uses PHP `dns_get_record` / a WHOIS HTTP API).
- Signals: domain age < 30 days (+25), no MX record (+15), no SPF TXT (+10). Unknown = 0 (mirror the
  email-auth "absence ≠ failure" rule).
- Config: `sendlock.dns.driver` (default `null`), cache results (TTL) to avoid repeat lookups.

**Needs to function**
- `config/sendlock.php` key + `.env` `SENDLOCK_DNS_DRIVER`. WHOIS age may need an API key
  (`WHOIS_API_KEY`) — document in `.env.example`. Cache table/store.

**Files**: `app/services/Dns/*` (new); `config/sendlock.php`; `.env.example`; compose into
`DomainIntelligenceService` or `RiskEngine`.

**Acceptance** (`tests/Feature/DnsIntelligenceTest.php`)
- Null driver adds 0. Fake live driver (injected) with age=2 days + no MX raises score.
- No real network call in the test suite.

**Depends on:** B1 (same service area). **Est:** M.

---

## Ticket B3 — Recipient Intelligence + history store (§12, "the differentiator")
**Goal:** score recipients by relationship/history, detect new/internal/external recipients.

**Build**
- Migration `recipient_interactions` (`organization_id`, `recipient_email`, `first_seen_at`,
  `last_seen_at`, `send_count`, `verified_count`). Recorded on every Send Protected attempt.
- `app/services/RecipientIntelligenceService.php` → `analyze(string $recipientEmail, int $orgId)`:
  - **New recipient** (never seen) → +20.
  - **Internal vs external** (domain == an org domain) → internal lowers, external neutral.
  - **Relationship/frequency score**: many prior verified sends → reduce risk (trusted relationship);
    first-time + financial content → raise.
- Compose into the outbound path (`ProtectedEmailController` → `RiskEngine`/workflow). Recipient is the
  counterparty here, distinct from sender-domain analysis.

**Needs to function**
- Org "own domains" list (add to `organizations` or a `trusted_domains` flag) to classify internal vs
  external. History table populated going forward.

**Files**: migration; `RecipientInteraction` model; `app/services/RecipientIntelligenceService.php`;
`ProtectedEmailController` (record + use); compose in workflow.

**Acceptance** (`tests/Feature/RecipientIntelligenceTest.php`)
- First send to a new external recipient flags "new recipient".
- After N verified sends, the same recipient scores lower.
- Internal recipient classified internal.

**Depends on:** none (but pairs with A2 confidence). **Est:** M.

---

# SPRINT C — AI content + RBAC alignment

## Ticket C1 — AI content classification (§10, the "AI-Powered" engine)
**Goal:** add a Claude-backed content classifier as a composed signal — the product's headline capability.

**Build**
- `app/services/Ai/ContentClassifier.php` interface + `NullContentClassifier` (default — returns no
  signal, keeps tests/offline runs free) and `ClaudeContentClassifier` (calls the Anthropic API via
  Laravel `Http`, no SDK; model `claude-haiku-4-5` for cost or `claude-sonnet-4-6` for accuracy).
- Returns structured `{categories:[], intent, score, findings, confidence}` for: invoice/banking-change/
  payroll/gift-card fraud, executive impersonation, manipulation/urgency, contract/financial sensitivity.
- Compose into `RiskEngine` **after** the rule-based `ContentIntelligenceService` (rules as cheap
  pre-filter, AI as the deep pass). Respect the existing content cap interplay.
- Driver config `sendlock.ai.driver` (default `null`); strict timeout + graceful degrade to rules-only
  on API error (mirror Twilio's degrade-to-stub pattern).

**Needs to function**
- `.env`: `SENDLOCK_AI_DRIVER=claude`, `ANTHROPIC_API_KEY`. `config/sendlock.php` ai block (model,
  timeout, max_tokens). **Before building, consult the `claude-api` skill for current model IDs,
  pricing, and the tool-use/JSON-output pattern** — do not hard-code model assumptions.
- Prompt returns strict JSON (use a tool/`response_format`-style contract) so parsing is deterministic.

**Files**: `app/services/Ai/*` (new); `config/sendlock.php`; `.env.example`; `RiskEngine` compose.

**Acceptance** (`tests/Feature/AiContentTest.php`)
- Null driver: 0 score, suite offline. Claude driver path covered with `Http::fake()` returning a
  canned JSON verdict → score/findings parsed correctly; API failure degrades to rules-only (no throw).

**Depends on:** A2 (confidence field). **Est:** M–L.

---

## Ticket C2 — PII / sensitive-data detection (§10)
**Goal:** flag outbound PII/PCI so misdirected sensitive data is caught.

**Build**
- `app/services/PiiDetectionService.php`: regex/NER for SSN, credit-card (Luhn-checked), passport,
  national ID, bulk email/phone lists; classify document sensitivity. Score scales with volume +
  whether recipient is external (ties into B3). Cap to avoid dominating.
- Compose into `RiskEngine`.

**Needs to function**: none external (regex + Luhn). Optionally reuse C1's classifier for free-text PII.

**Files**: `app/services/PiiDetectionService.php`; `RiskEngine` compose.

**Acceptance** (`tests/Feature/PiiDetectionTest.php`): valid card number (passes Luhn) flags; random
16 digits that fail Luhn do not; SSN pattern flags.

**Depends on:** B3 (external-recipient weighting, optional). **Est:** S–M.

---

## Ticket C3 — RBAC reconciliation: Finance + Compliance roles (§6)
**Goal:** align roles/permissions with the spec so financial approval & compliance review exist.

**Build**
- In `RolesAndPermissionsSeeder`: add **Finance Officer** (`approve emails`, `manage approvals`,
  `view reports`, finance-scoped) and **Compliance Officer** (`view audit logs`, `view reports`).
  Document the mapping for renamed roles (Manager↔Department Manager, Employee↔Standard User,
  Security Officer↔Security Administrator) in CLAUDE.md rather than renaming (avoids churn) — or rename
  if the team prefers spec vocabulary (decide first).
- Add `isFinanceOfficer()` / `isComplianceOfficer()` helpers on `User`; wire into sidebar visibility.
- Route the Finance approval stage (needed by D1) to Finance Officer.

**Needs to function**: re-seed (`php artisan db:seed --class=RolesAndPermissionsSeeder`). Tests must seed.

**Files**: `database/seeders/RolesAndPermissionsSeeder.php`; `app/Models/User.php`;
`layouts/navigation.blade.php`; CLAUDE.md (role-mapping note).

**Acceptance** (`tests/Feature/RoleMatrixTest.php`): Finance Officer can reach approvals but not user
management; Compliance Officer sees audit logs read-only.

**Depends on:** none; **blocks** D1. **Est:** S.

---

# SPRINT D — Approval workflow redesign

## Ticket D1 — Configurable, multi-stage approval chains (§14)
**Goal:** replace the single linear gate with ordered/parallel chains incl. Finance/Legal + escalation.

**Build**
- New models/migrations:
  - `approval_chains` (per org; optional scoping by risk_level / amount threshold / content category).
  - `approval_chain_stages` (ordered; `approver_role`, `mode` = sequential|parallel, `sla_minutes`).
  - Extend `approval_requests` with `current_stage`, link to chain; `approval_actions` already records
    per-approver decisions.
- Rework `ApprovalWorkflow`:
  - On create, resolve the matching chain for the org + risk + amount; set first stage.
  - `approve()` advances sequential stages / waits for quorum on parallel stages before moving on.
  - **Escalation**: a stage past its `sla_minutes` escalates (scheduled job) to the next role / admin.
  - **Emergency override**: an authorized role (e.g. Org Admin) can force-release with a logged reason.
- UI: chain builder for admins; approver queue shows current stage; override action.

**Needs to function**
- Seeded default chain (Employee→Manager→Finance→Release for financial; Manager-only otherwise) so
  existing flows keep working. A scheduler entry for escalation (`composer dev` already runs the queue;
  add `schedule()` for the escalation sweep). Mobile approval = responsive approver view (no native app).

**Files**: migrations + `ApprovalChain`, `ApprovalChainStage` models; `app/services/ApprovalWorkflow.php`
(major rework); `ApprovalController` + Blade (queue, chain builder, override); `app/Console` schedule.

**Acceptance** (`tests/Feature/ApprovalChainTest.php`)
- Sequential chain requires each role in order; parallel stage needs quorum.
- Financial email routes through Finance stage; non-financial does not.
- SLA breach escalates; emergency override releases and writes an audit row.
- Existing `ApprovalWorkflowTest`/`ApprovalTest` updated and green.

**Depends on:** C3 (Finance Officer role), A3 (override must audit). **Est:** L.

---

# SPRINT E — Heavy / external integrations (dedicated phases)

## Ticket E1 — Real attachment parsing + OCR (§11)
**Goal:** inspect actual file bytes, not just filenames — the largest single capability gap.

**Build**
- Real upload handling (store to a scoped, non-public disk; size/type limits).
- `app/services/Attachment/AttachmentParser.php` driver interface + `NullAttachmentParser` (default,
  filename-only — current behaviour) and `LiveAttachmentParser`:
  - PDF/Office text extraction; **macro detection** (scan OOXML `vbaProject.bin` / OLE streams);
    embedded-URL extraction (feed into `UrlInspectionService`); archive expansion (bounded) to inspect
    contents; **OCR** for images/scanned PDFs (Tesseract via CLI or an OCR API driver).
  - Reuse `ContentIntelligenceService`/C1 on extracted text; sensitive-document classification.
- Keep `AttachmentAnalysisService` as the filename pre-check; parser adds the deep pass.

**Needs to function**
- File storage config; Tesseract binary **or** OCR API key (`.env`), and a PDF/Office text lib
  (e.g. `smalot/pdfparser`, `phpoffice/*`) — add to `composer.json`. Sandbox/queue heavy parsing
  (use the existing queue). Document all deps in `.env.example` + CLAUDE.md.

**Files**: upload form + controller; `app/services/Attachment/*`; `config/sendlock.php`; `composer.json`;
`RiskEngine`/scan flow compose; queued job for parsing.

**Acceptance** (`tests/Feature/AttachmentParsingTest.php`): null driver = current behaviour; live driver
(with a fixture docm) detects a macro; embedded URL in a PDF fixture flags via URL inspection; OCR path
faked. Heavy work runs on the queue.

**Depends on:** A1 isn't required; pairs with C1. **Est:** XL.

---

## Ticket E2 — External threat-intelligence feeds (§8)
**Goal:** enrich decisions with real reputation sources behind a normalized cache.

**Build**
- `app/services/ThreatFeeds/ThreatFeed.php` interface; drivers per source — start with the highest-ROI:
  **Google Safe Browsing**, **VirusTotal**, **PhishTank/OpenPhish**, **URLHaus**, **AbuseIPDB**,
  **Spamhaus**. Each normalizes to `{indicator, type, severity, source}`.
- A normalized cache table `threat_intel_cache` (indicator, verdict, fetched_at, TTL) so lookups are
  cheap and offline-repeatable; a scheduled importer for the list-based feeds (PhishTank/URLHaus).
- Extend `ThreatIntelligenceService` to consult cache → live feeds (driver) → fall back to the existing
  internal `threat_intel_domains` list. Keep domain match; add URL/IP/hash where the source supports it.

**Needs to function**
- API keys per provider in `.env` (`VIRUSTOTAL_API_KEY`, `GSB_API_KEY`, `ABUSEIPDB_KEY`, …) +
  `config/sendlock.php` feed block; default driver `null`/internal so nothing calls out without keys.
  Respect provider rate limits (cache + queue).

**Files**: `app/services/ThreatFeeds/*`; migration (cache table); scheduled importer command;
`ThreatIntelligenceService` (compose); `config/sendlock.php`; `.env.example`.

**Acceptance** (`tests/Feature/ThreatFeedsTest.php`): internal/null path = today's behaviour; each
driver covered with `Http::fake()` canned responses; cache hit avoids a second HTTP call; importer
populates the cache from a faked feed.

**Depends on:** none. **Est:** L.

---

## Ticket E3 — Verification portal + QR channel (§13)
**Goal:** complete the verification channels the spec lists (portal + QR) beyond SMS/WhatsApp/email OTP.

**Build**
- A signed, expiring recipient-facing **verification portal** route (no auth; token-scoped) where a
  recipient confirms identity; ties back to the `ApprovalRequest`.
- **QR channel**: render a QR encoding the portal token for in-person/visual verification; add as a
  `VerificationChannel` implementation alongside SMS/WhatsApp/email.

**Needs to function**: signed-URL config + a QR generator lib (e.g. `simplesoftwareio/simple-qrcode` or
`endroid/qr-code`) in `composer.json`. TTL from existing verification config.

**Files**: `app/services/Verification/QrVerificationChannel.php`; portal controller + Blade;
signed routes; `composer.json`.

**Acceptance** (`tests/Feature/VerificationPortalTest.php`): valid token confirms & advances the
request; expired/invalid token rejected; QR encodes the correct signed URL.

**Depends on:** none (builds on existing verification). **Est:** M.

---

# SPRINT F — Platform / architecture (vision items; schedule if/when needed)

## Ticket F1 — REST API layer + API Gateway (§1 diagram, §17 "API-first")
**Build:** versioned `/api/v1` routes (Sanctum tokens per org), exposing scan/evaluate, approvals,
trust-center, threat-intel; rate limiting; OpenAPI spec. Reuse the same services/`RiskEngine` — controllers
become thin. **Needs:** `laravel/sanctum`, per-org API tokens, throttling middleware. **Est:** L.

## Ticket F2 — SIEM integration (§1: Splunk / Sentinel)
**Build:** an audit/threat **event exporter** driver (syslog/HEC/webhook) streaming audit + detection
events. **Needs:** SIEM endpoint config + token in `.env`; behind a `null` default driver. Builds on A3.
**Est:** M.

## Ticket F3 — Email-client add-ins & mobile app (§1 diagram)
**Out of scope for the Laravel app** — these are separate Outlook/Gmail add-in projects and a mobile
client consuming F1's API. Track as a program-level epic, not a backend ticket. Document the API
contract (F1) as their dependency.

---

# Suggested ordering & dependency graph

```
A1 ─┐
A2 ─┼─► (independent, ship first — no external deps)
A3 ─┘        │
             ├─► D1 needs A3 (override audit) + C3 (Finance role)
B1 ─► B2     │
B3 ───────────┘ (pairs with A2 confidence)
C1 ─► C2 (C1 needs A2)
C3 ───────────► D1
E1, E2, E3  (parallel, external deps — dedicated phases)
F1 ─► F2, F3 (vision; after core engines stable)
```

**Recommended sprint sequence:** A → B → C → D, then E (parallel tracks) and F as the platform matures.
Sprints A–B add real detection value with zero external dependencies and keep the test suite fully
offline; C introduces the first paid external dependency (Anthropic API) behind a null-default driver.

## Definition of Done (every ticket)
1. Logic in a composed service (no inline scoring); tenant-scoped; mutations audited.
2. External calls behind a driver with a safe default; nothing bills without `.env` creds.
3. Pest tests green, suite still runs offline (`Http::fake()` / null drivers), `RolesAndPermissionsSeeder` seeded.
4. `vendor/bin/pint` clean.
5. `.env.example`, `config/sendlock.php`, and CLAUDE.md updated for any new config/driver.
6. Placeholder routes replaced; sidebar/role visibility wired.
