# SendLock — Spec vs. Implementation Gap Analysis

Compares the **SendLock System Specification** (the full product vision) against the
**current codebase** as of branch `sprint-1-finalization`. For each spec area: what exists,
what is stubbed, and what is missing — with concrete file/code references so the next
build phase has a precise starting point.

Legend: ✅ implemented · ⚠️ partial / stubbed · ❌ not started

> Source of truth for "implemented" is the actual code in `app/services/`,
> `app/Http/Controllers/`, and `database/`, not the roadmap prose. Scores and thresholds
> quoted below are read directly from the service classes.

---

## At-a-glance

| Spec § | Capability | Status | One-line gap |
|---|---|---|---|
| 4 | Engine-per-capability architecture | ✅ | All signal logic lives in composable `App\Services\*` services behind `RiskEngine` |
| 5 | Multi-tenant isolation | ✅ | Manual `organization_id` scoping in every controller; no global scope |
| 6 | RBAC | ⚠️ | 7 roles seeded, but names/permissions diverge from spec (see below) |
| 7 | Domain Intelligence | ⚠️ | 4 of ~25 spec checks; no WHOIS/DNS/age/homograph/entropy |
| 8 | Threat Intelligence | ⚠️ | Internal DB list only; zero external feeds wired |
| 9 | Email Intelligence | ⚠️ | SPF/DKIM/DMARC only; no header/Reply-To/display-name analysis |
| 10 | Content Intelligence | ⚠️ | Static phrase weights; no AI/ML, no PII detection |
| 11 | Attachment Intelligence | ⚠️ | **Filename extension only** — no file parsing/OCR/macro extraction |
| 12 | Recipient Intelligence | ⚠️ | Verified-list lookup only; no relationship/frequency/history scoring |
| 13 | Recipient Verification | ✅ | SMS/WhatsApp/email + Twilio (degrades to log stub); no QR/portal |
| 14 | Approval Workflow | ⚠️ | Linear state machine; no configurable/parallel/escalation chains |
| 15 | Risk Scoring | ⚠️ | 0–100 + level + findings; **no confidence score**, no recommendations field |
| 16 | Audit & Compliance | ⚠️ | Logged via `AuditLogger`; **not immutable**, not all events covered |
| 17 | Design principles | ⚠️ | Modular/multi-tenant ✅; not microservice/API-first/cloud-native yet |

---

## §6 — Role-Based Access Control

**Seeded roles** (`database/seeders/RolesAndPermissionsSeeder.php`):
Super Admin, Head Organization Admin, Organization Admin, Manager, Employee,
Security Officer, Auditor.

**Spec roles:** Super Admin, Organization Admin, Security Administrator, Department Manager,
Compliance Officer, Auditor, Finance Officer, Standard User.

| Spec role | Closest current role | Gap |
|---|---|---|
| Super Admin | Super Admin | ✅ matches |
| Organization Admin | Organization Admin | ✅ (plus an extra "Head Organization Admin" tier the spec doesn't mention — this is the org-hierarchy feature) |
| Security Administrator | Security Officer | Name differs; permissions roughly align (`manage policies`, `view audit logs`) |
| Department Manager | Manager | Name differs; no department-scoped approval concept yet |
| Compliance Officer | — | ❌ missing (Auditor partially covers read-only compliance) |
| Finance Officer | — | ❌ missing — **notable**, since the spec makes Finance a distinct approver for high-risk financial comms (§14) |
| Standard User | Employee | Name differs |
| Auditor | Auditor | ✅ matches |

**Permissions** are coarse (11 permissions, e.g. `manage domains`, `approve emails`). The spec
implies finer separation (e.g. finance-only approval, compliance-only log access) that the
current matrix can't express. No per-department scoping exists.

**To close:** decide whether to rename roles to spec vocabulary or keep current names and
document the mapping; add `Finance Officer` + `Compliance Officer` if the financial-approval
and compliance-review flows are on the roadmap.

---

## §7 — Domain Intelligence Engine

**Implemented** (`app/services/DomainIntelligenceService.php`):
- Blocklist membership → instant **100** (short-circuits the whole engine).
- Trust-list membership → benign; **absence of trust → +70** (drives bare unknown domains to HIGH).
- Typosquat token match (hard-coded list: `micros0ft`, `amaz0n`, `paypa1`, …) → **+50**.
- Levenshtein lookalike of a trusted vendor domain (edit distance 1–2) → **+50**.
- Emits structured `domain_flags` persisted by `FlaggedDomainService` for repeat-use warnings.

**Spec asks for ~25 checks.** Missing:

| Spec check | Status |
|---|---|
| Homograph / Unicode / IDN spoofing | ❌ |
| Character substitution/omission/insertion/duplication/transposition (general, not a fixed token list) | ❌ (only the 7 hard-coded tokens) |
| Domain entropy analysis | ❌ |
| Domain age / WHOIS | ❌ |
| DNS / MX / reverse-DNS validation | ❌ |
| Suspicious TLDs (domain-level) | ⚠️ only at URL level (`UrlInspectionService`) |
| Disposable domains | ❌ |
| Subdomain abuse | ❌ |
| ARC / BIMI | ❌ |
| SPF/DKIM/DMARC | ⚠️ in a separate service (`EmailAuthenticationService`), unknown-by-default |

**To close:** the typosquat list should become an algorithmic edit-distance/homograph check; WHOIS/DNS
require an outbound resolver driver (fits the existing driver pattern in `config/sendlock.php`).

---

## §8 — Threat Intelligence Engine

**Implemented** (`app/services/ThreatIntelligenceService.php`): matches the sender domain against the
platform-wide `threat_intel_domains` table (curated by Super Admin via `ThreatIntelController`).
Severity → score: HIGH 70 / (default) 40 / LOW 20.

**Spec asks for** VirusTotal, Google Safe Browsing, Cisco Talos, Spamhaus, PhishTank, OpenPhish,
URLHaus, AbuseIPDB, AlienVault OTX, MISP — **none are wired**. There is no caching/normalization
layer, no IP/URL/hash reputation (only domain), and no scheduled feed ingestion.

**To close:** add feed-driver interfaces (mirroring the verification-driver pattern), a normalized
cache table, and a scheduled importer. The current `analyze(string $domain)` signature is the right
seam to layer external lookups behind.

---

## §9 — Email Intelligence Engine

**Implemented** (`app/services/EmailAuthenticationService.php`): SPF/DKIM/DMARC only, and only an
**explicit failure** adds score (SPF +15, DKIM +10, DMARC +20, capped 35). Default `null` driver
returns "unknown" for all three → no score. Per-message results can be injected and win over the driver.

**Spec asks for** sender/recipient reputation, **display-name spoofing**, **Reply-To mismatch**,
**Return-Path mismatch**, header anomalies, routing anomalies, communication history — **all ❌**.
These are high-value, low-cost BEC signals (display-name and Reply-To spoofing especially) and are
the most impactful near-term additions for an outbound/inbound BEC platform.

**To close:** a new `HeaderIntelligenceService` composed into `RiskEngine`, taking parsed headers
(From display name vs. address, Reply-To, Return-Path). Communication history needs a sender/recipient
interaction store.

---

## §10 — Content Intelligence Engine

**Implemented** (`app/services/ContentIntelligenceService.php`): case-insensitive substring match
against a static phrase→weight map (bank-change 35, wire transfer 25, urgency 25, invoice 10,
logistics/insurance cues …), capped at **65**.

**Spec asks for** AI/ML classification, PII detection, gift-card fraud, payroll changes, contracts,
sensitive-corporate-info classification, manipulation/pressure-language modelling. Current coverage
is rule-based and English-only; **no AI engine** despite the product's "AI-Powered" positioning. No
PII/PCI detection. The §1 diagram's "AI Security Engine / ML models / zero-day detection" block is
entirely aspirational today.

**To close:** this is where an LLM (Claude) classifier would slot in as another composed service
returning `{score, findings}` — keeps `RiskEngine` additive. PII detection can be regex+NER.

---

## §11 — Attachment Intelligence Engine

**Implemented** (`app/services/AttachmentAnalysisService.php`): **filename-level only.** Flags
dangerous extensions (exe/scr/bat/js/…) +40, macro Office docs (docm/xlsm/…) +30, archives +15,
double-extension disguise (`invoice.pdf.exe`) +35; capped 50. Input is one filename per line — **no
actual file bytes are ever read.**

**Spec asks for** real parsing of PDF/Word/Excel/PPT/ZIP/RAR/images, **OCR**, macro extraction,
embedded-URL/hidden-executable detection, sensitive-document classification — **all ❌**. This is the
largest single capability gap: today an attacker's `report.pdf` containing a malicious macro/URL is
invisible because only the filename is inspected.

**To close:** requires real file upload + a parsing/sandbox driver. Significant scope; good candidate
for a dedicated phase.

---

## §12 — Recipient Intelligence Engine ("the primary differentiator")

**Implemented:** `RiskEngine` treats the counterparty domain's trust status as the main recipient
signal, and `ApprovalWorkflow` checks the `verified_recipients` list to skip re-verification.

**Spec asks for** previous communication history, relationship score, recipient trust/frequency score,
new-recipient detection, internal-vs-external classification — **all ❌**. There is no per-recipient
interaction history or scoring model; "new recipient" can't be detected because past sends aren't
tracked per recipient.

**To close:** a recipient-history store (counts, first-seen, last-seen per `organization_id` +
recipient) feeding a new `RecipientIntelligenceService`.

---

## §13 — Recipient Verification Center

**Implemented** (`app/services/Verification/*`, `RecipientVerificationController`): 6-digit codes with
configurable TTL over SMS / WhatsApp / email. `TwilioVerificationChannel` sends real SMS/WhatsApp via
Twilio REST and **degrades to the log stub when credentials are absent** — nothing bills until
`SENDLOCK_VERIFICATION_DRIVER=twilio` + `TWILIO_*` are set. Tests use `Http::fake`. ✅ closest area to
spec.

**Spec also lists** OTP (✅, that's the code), secure verification portal (❌), QR verification (❌).
Verification is triggered by the workflow for high-risk decisions but isn't yet per-content-type
configurable (invoices vs. contracts vs. bank-change all flow through the same risk gate).

**To close:** minor — add a recipient-facing portal/QR channel if required; otherwise this area is
production-shaped.

---

## §14 — Approval Workflow Engine

**Implemented** (`app/services/ApprovalWorkflow.php`): a **linear** state machine driven by the risk
decision — ALLOW→RELEASED, MANAGER_APPROVAL→PENDING_APPROVAL, RECIPIENT_VERIFY→verify-then-approve,
QUARANTINE→BLOCKED. Single approval step; `nextStatus()` clears one requirement at a time.

**Spec asks for** configurable chains (Employee→Manager→Finance→Legal→Release), **parallel** approvals,
**sequential** multi-stage, **escalation**, **emergency override**, **mobile approval** — **all ❌**.
Today there is exactly one approval gate and no notion of an ordered/branching approver chain or a
Finance/Legal stage.

**To close:** introduce an approval-chain definition (per org / per risk-level / per amount) and a
multi-step `ApprovalStage` model. This is a substantial redesign of the current single-gate model.

---

## §15 — Risk Scoring Engine

**Implemented** (`app/services/RiskEngine.php`): single additive 0–100 score composed from all signal
services; `≥90 CRITICAL/QUARANTINE, ≥70 HIGH/RECIPIENT_VERIFY, ≥30 MEDIUM/MANAGER_APPROVAL, else
LOW/ALLOW`. Returns `findings` (the evidence list). ✅ explainability via findings.

**Spec asks for** Safe / Low / Medium / High / Critical (current uses LOW…CRITICAL — **no "Safe" band**),
plus per-score **confidence** and **recommendations** fields — **both ❌**. Findings exist; structured
evidence/recommendation/confidence do not.

**To close:** small, additive change — extend the `result()` payload with `confidence` and
`recommendations`, and optionally split LOW into Safe/Low.

---

## §16 — Audit & Compliance

**Implemented** (`app/helpers/AuditLogger.php`): `log($action, $entityType, $entityId, $description)`
auto-captures `organization_id`, `user_id`, IP; called on user create/update/activate/deactivate/delete.
UI at `audit-logs/`.

**Spec asks for** logging of **every** security event (login/logout, role changes, approval decisions,
verification events, threat detections, policy changes) and **immutable** logs. Today coverage is
mostly user-management mutations; auth events, approval/verification/threat events, and policy changes
are **not consistently logged**, and rows are ordinary mutable DB records (no append-only/hash-chain
guarantee).

**To close:** broaden `AuditLogger` call sites (especially approvals, verification, trust-center, and
threat-intel changes) and add tamper-evidence (append-only + row hash chaining) if compliance claims
are to be made.

---

## §17 / §4 — Architecture & design principles

✅ **Modular** (engine-per-service), ✅ **multi-tenant**, ✅ **secure-by-default** scoping,
✅ **extensible** (driver pattern for verification/email-auth/threat).

❌ **API-first / REST API Gateway** (§1 diagram) — there is no API layer; everything is Blade web
controllers. ❌ **Microservice-ready / cloud-native** — single Laravel monolith (which is fine, but
diverges from the stated vision). ❌ **SIEM integrations** (Splunk/Sentinel in the diagram). ❌
**Mobile app / Outlook & Gmail add-ins** (§1 diagram).

---

## Recommended sequencing (highest value ÷ effort first)

1. **Email/header intelligence (§9)** — display-name & Reply-To/Return-Path spoofing are cheap, high-signal BEC checks; pure logic, no external deps.
2. **Risk payload completeness (§15)** — add `confidence` + `recommendations`, optional "Safe" band. Trivial, improves every consumer.
3. **Audit coverage + immutability (§16)** — broaden call sites and add hash-chaining; needed for any compliance claim.
4. **Algorithmic domain checks (§7)** — replace the 7-token typosquat list with edit-distance/homograph detection (no network).
5. **Recipient intelligence (§12)** — requires a history store; unlocks the spec's "primary differentiator".
6. **AI content classification (§10)** — slot a Claude-backed classifier in as a composed service; aligns the product with its "AI-Powered" positioning.
7. **Configurable approval chains (§14)** — larger redesign; Finance/Legal stages + escalation.
8. **Real attachment parsing + threat feeds (§11, §8)** — heaviest (external services/sandbox); schedule as dedicated phases.

*Each new signal must be a service returning `{score, findings}` composed by `RiskEngine` — never
inline scoring — per the established architecture.*
