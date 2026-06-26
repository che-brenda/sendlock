# SendLock — Cost-Tiered Rollout Roadmap

Organizes the work in [SPRINT_BACKLOG.md](SPRINT_BACKLOG.md) by **cost tier**, so we ship everything
free first, layer in free/low-cost feeds during beta, and gate paid services behind production
customers. The driver pattern (`config/sendlock.php`, `null`/log defaults) is what makes this possible:
the *same* service code runs on a free driver in dev/beta and a paid driver in production — only `.env`
changes.

| Tier | When | Cost | Driver default in code |
|---|---|---|---|
| 🟢 Tier 1 | Build immediately | Free (self-hosted / offline) | enabled by default |
| 🟡 Tier 2 | Beta | Free / free-tier API keys | `null` until key set |
| 🔴 Tier 3 | Production customers | Paid | `null`/log stub until creds set |

**Key principle:** a feature's *code* ships in Tier 1–2; the *paid provider* behind it is just a driver
swap in Tier 3. E.g. AI classification ships running on **Gemini free tier** (beta) and is promoted to
**Claude** (production) with no code change — only `SENDLOCK_AI_DRIVER`.

---

# 🟢 TIER 1 — Build Immediately (Free, no external paid deps)

Everything here is pure PHP, self-hosted binaries, or already built. No API keys, no billing.
**The whole test suite stays offline.**

| Feature | Current status | Action | Ref |
|---|---|---|---|
| Authentication & RBAC | ✅ built (7 roles) | Add Finance/Compliance roles, document mappings | [Backlog C3](SPRINT_BACKLOG.md) |
| Trusted & Blocked Domains | ✅ built (Trust Center) | none — maintain | §13 spec |
| SPF/DKIM/DMARC validation | ⚠️ built, `null` driver (unknown-by-default) | Add a **local DNS** resolver driver (below) so SPF/DMARC TXT are actually read | [Backlog B2](SPRINT_BACKLOG.md) |
| Risk Engine | ✅ built (additive, 0–100) | Add `confidence` + `recommendations` + Safe band | [Backlog A2](SPRINT_BACKLOG.md) |
| Audit Logs | ⚠️ built, partial coverage | Full coverage + immutability (hash chain) | [Backlog A3](SPRINT_BACKLOG.md) |
| Domain Intelligence | ⚠️ 4 checks | Expand: entropy, disposable, subdomain abuse, brand edit-distance | [Backlog B1](SPRINT_BACKLOG.md) |
| Typosquatting detection | ⚠️ 7 hard-coded tokens | Replace with **algorithmic** Levenshtein + keyboard-adjacency vs. brand/vendor list | [Backlog B1](SPRINT_BACKLOG.md) |
| Homograph detection | ❌ | Confusable-char map + mixed-script/IDN (`xn--`) detection | [Backlog B1](SPRINT_BACKLOG.md) |
| DNS analysis | ❌ | Local resolver driver: MX present, SPF TXT present, reverse-DNS, (age via WHOIS = Tier 2/3) | [Backlog B2](SPRINT_BACKLOG.md) |
| Tesseract OCR | ❌ | Self-hosted Tesseract binary for image/scanned-PDF text → feeds attachment + content engines | [Backlog E1](SPRINT_BACKLOG.md) |

### T1 tickets

**T1.1 — Domain/typosquat/homograph (free, offline)** ✅ **SHIPPED** → `DomainIntelligenceService`
rewritten with algorithmic typosquatting (confusable de-obfuscation + edit-distance vs. a built-in
brand list), homograph/IDN (punycode + non-ASCII), brand-as-subdomain abuse, disposable domains,
high-risk TLDs, and random-looking (DGA) labels. New `domain_flags` types ranked in
`FlaggedDomainService::SEVERITY` and labelled in the flagged-domain views. Covered by
`tests/Feature/DomainIntelligenceTest.php` (incl. a no-false-positive guard for ordinary vendor names).

**T1.2 — Local DNS analysis driver (free)** → Backlog **B2**, but split out the no-cost checks.
Build `LocalDnsResolver` using PHP `dns_get_record()` / `checkdnsrr()` — MX presence, SPF/DMARC TXT
presence, reverse-DNS. **Domain age/WHOIS is deferred** (needs a paid/limited API → Tier 2/3).
Needs: nothing external (uses the host's DNS). `SENDLOCK_DNS_DRIVER=local`. Default stays `null` for
tests so the suite makes no DNS calls; inject the resolver in tests.

**T1.3 — Tesseract OCR (free, self-hosted)** ✅ **SHIPPED** → `app/services/Ocr/` (`OcrDriver`
interface + `NullOcrDriver` default + `TesseractOcrDriver` + `OcrService`), driver bound in
`AppServiceProvider` from `sendlock.ocr.driver`. The email-scan form takes an optional uploaded
file; its OCR'd text is folded into the analysed content. Config + `.env.example` keys
(`SENDLOCK_OCR_DRIVER`, `TESSERACT_BINARY`, `SENDLOCK_OCR_TIMEOUT`) added. Covered by
`tests/Feature/OcrTest.php` (fake driver bound in the container — suite stays offline).
**To fully enable:** install the Tesseract binary on the host and set `SENDLOCK_OCR_DRIVER=tesseract`.
*Follow-ons (not in this slice): PDF/Office text extraction, macro detection, queueing heavy OCR —
tracked under Backlog E1 / Tier 3 enterprise OCR.*

**T1.4 — Risk payload** ✅ **SHIPPED** → `RiskEngine` now returns `confidence` (0–100) +
decision-derived `recommendations`, and a **SAFE** band (`<10`); both persisted on `email_scans`
and `approval_requests` (migration `2026_06_26_120000`), surfaced in the scan UI. Covered by
`tests/Feature/RiskEngineTest.php`. *(Audit hardening A3 still pending.)*

**T1.5 — Header intelligence** ✅ **SHIPPED** → `app/services/HeaderIntelligenceService.php`,
composed into `RiskEngine`, wired into the email-scan form, covered by
`tests/Feature/HeaderIntelligenceTest.php`. Display-name / Reply-To / Return-Path spoofing.
Free, offline, high BEC value.

**Tier-1 Definition of Done:** product is fully usable and self-hostable with **zero** paid services and
an offline test suite.

---

# 🟡 TIER 2 — Add During Beta (Free or free-tier API keys)

External lookups behind drivers, all on **free tiers**. Each is `null`-default so the app runs without
keys; beta deployments set the keys. **Respect free-tier rate limits with caching + the queue.**

| Feature | Type | Free-tier note | Slots into |
|---|---|---|---|
| Google Safe Browsing | URL/domain reputation | Free with API key, generous quota | `ThreatFeed` driver — Backlog **E2** |
| VirusTotal (free tier) | domain/URL/file reputation | Free key, **~4 req/min** — cache hard | `ThreatFeed` driver — Backlog **E2** |
| AbuseIPDB (free tier) | IP reputation | Free key, daily cap | `ThreatFeed` driver — Backlog **E2** |
| OpenPhish | phishing URL feed | Free community feed (list) | scheduled importer → `threat_intel_cache` |
| PhishTank | phishing URL feed | Free with registration (list) | scheduled importer → `threat_intel_cache` |
| Gemini API (free tier) | AI content classification | Free tier rate-limited | `ContentClassifier` driver (see below) |

### T2 tickets

**T2.1 — Threat-feed drivers (free tiers)** ✅ **SHIPPED** (Safe Browsing + VirusTotal) →
`app/services/ThreatFeeds/` (`ThreatFeed` interface + `GoogleSafeBrowsingFeed` + `VirusTotalFeed`),
verdicts normalized + cached in `threat_intel_cache` (`ThreatIntelCache`, migration `2026_06_26_130000`).
`ThreatIntelligenceService` now checks curated list → cache → live feeds. Opt-in via
`SENDLOCK_THREAT_FEEDS` + per-feed API key (default empty = no calls); failures degrade to no score.
Covered by `tests/Feature/ThreatFeedsTest.php` (`Http::fake`, cache-hit-avoids-2nd-call, curated
precedence, graceful degrade). *Follow-on: AbuseIPDB (IP-based, needs an IP signal) — not yet wired.*

**T2.2 — Phishing list importers (free)** ✅ **SHIPPED** → `app/Console/Commands/ImportThreatFeeds.php`
(`sendlock:import-threat-feeds`) pulls OpenPhish (no key) + PhishTank (optional key) into
`threat_intel_cache`; scheduled hourly in `routes/console.php`, **no-op unless `SENDLOCK_THREAT_LISTS`
is set** (safe to schedule unconditionally — never fetches without opt-in). Covered by
`tests/Feature/ThreatListImportTest.php` (`Http::fake`, URL→domain extraction, graceful fetch failure).

**T2.3 — AI content classification on Gemini (free tier)** ✅ **SHIPPED** → `app/services/Ai/`
(`ContentClassifier` interface + `NullContentClassifier` default + `GeminiContentClassifier`), bound
in `AppServiceProvider` from `sendlock.ai.driver`, composed into `RiskEngine` after the rule-based
content pass (capped 50, degrades to no signal on any error). Config + `.env.example` keys
(`SENDLOCK_AI_DRIVER`, `GEMINI_API_KEY`, `GEMINI_MODEL`). Covered by `tests/Feature/AiContentTest.php`
(`Http::fake`, cap, graceful degrade, RiskEngine composition). Claude is the Tier 3 driver swap (T3.1).
*Original ticket detail:*
- `ContentClassifier` interface + `NullContentClassifier` (default) + **`GeminiContentClassifier`**
  (Laravel `Http` → Google Generative Language API, strict-JSON response).
- Compose into `RiskEngine` after the rule-based content service; degrade to rules-only on API
  error/timeout (mirror Twilio's degrade pattern).
- Needs: `.env` `SENDLOCK_AI_DRIVER=gemini`, `GEMINI_API_KEY`; `config/sendlock.php` ai block
  (provider, model, timeout, max_tokens). Free tier is rate-limited → only call on medium+ pre-score
  emails (rules act as a cheap gate), and cache by content hash.
- Tests: null driver offline; Gemini path with `Http::fake()` → parsed JSON verdict; failure degrades.

**Tier-2 Definition of Done:** beta deployments get real external reputation + AI classification at
**$0** (free tiers), with caching keeping usage inside quotas; the app still runs and tests still pass
with no keys set.

---

# 🔴 TIER 3 — Enable for Production Customers (Paid)

Same code, paid drivers — flipped on per production customer / plan. **Each is already shaped as a
driver swap thanks to Tiers 1–2.**

| Feature | Replaces / upgrades | What changes |
|---|---|---|
| Claude API | Gemini (T2.3) | `SENDLOCK_AI_DRIVER=claude` — promote AI classification to Claude for accuracy |
| Twilio SMS | log stub (✅ already built) | Set `SENDLOCK_VERIFICATION_DRIVER=twilio` + `TWILIO_*` — real SMS |
| WhatsApp Business API | log stub (✅ via Twilio WhatsApp) | Same Twilio driver, WhatsApp sender configured |
| Commercial threat feeds | free feeds (T2.1) | Add paid `ThreatFeed` drivers (e.g. Recorded Future, Mandiant) |
| Enterprise OCR | Tesseract (T1.3) | Swap `SENDLOCK_OCR_DRIVER` to a cloud OCR driver (e.g. AWS Textract / Azure) |

### T3 tickets

**T3.0 — Per-org plan/feature gate** ✅ **SHIPPED** → reuses the existing
`organizations.subscription_plan` column; `config('sendlock.plans')` maps plan → features and
`Organization::hasFeature()` is the gate. `RiskEngine` calls it before the AI classifier (only when a
non-null driver is set), so a free/beta org never triggers a paid provider. Covered by
`tests/Feature/PlanGateTest.php`. Plans: free / beta / pro / enterprise.

**T3.1 — Promote AI to Claude** ✅ **SHIPPED** → `ClaudeContentClassifier` (Anthropic Messages API,
raw HTTP, anthropic-version 2023-06-01, strict-JSON via `output_config.format`), registered in
`AppServiceProvider` for `SENDLOCK_AI_DRIVER=claude`. Model defaults to `claude-opus-4-8`
(`ANTHROPIC_MODEL` override) — per the `claude-api` skill (don't downgrade for cost; user's choice).
Pure driver swap, no `RiskEngine` change; degrades on error/refusal. Covered by
`tests/Feature/ClaudeContentTest.php` (`Http::fake`, header/schema assertion, refusal degrade, gate).

**T3.2 — Twilio SMS + WhatsApp (already built)** → just enablement + docs. `TwilioVerificationChannel`
exists and **degrades to the log stub without creds**. Needs: `SENDLOCK_VERIFICATION_DRIVER=twilio`,
`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM`/WhatsApp sender. Tests already fake the HTTP
client. Action: verify WhatsApp sender config + add a production-readiness checklist.

**T3.3 — Commercial threat feeds** → additional paid `ThreatFeed` drivers behind the T2.1 interface +
cache. Needs: vendor API keys/contracts; per-plan gating so only paying orgs trigger paid lookups.

**T3.4 — Enterprise OCR** → cloud OCR driver implementing the T1.3 OCR interface. Needs: cloud
credentials; per-plan gating; keep Tesseract as the free fallback.

**Tier-3 Definition of Done:** production customers get paid accuracy/scale via `.env`/plan flags only;
free/beta tiers are unaffected and continue working on free drivers.

---

# Build order (free value first, paid last)

```
🟢 TIER 1  (no deps, offline)            🟡 TIER 2 (free-tier keys)         🔴 TIER 3 (paid)
 T1.5 Header intel ───────────┐
 T1.4 Risk payload + Audit ────┤
 T1.1 Domain/typo/homograph ───┼──► T2.1 GSB/VT/AbuseIPDB feeds ──► T3.3 Commercial feeds
 T1.2 Local DNS ───────────────┘     T2.2 OpenPhish/PhishTank
 T1.3 Tesseract OCR ─────────────────────────────────────────────► T3.4 Enterprise OCR
                                      T2.3 Gemini AI classify ─────► T3.1 Claude AI classify
 (Twilio already built) ─────────────────────────────────────────► T3.2 Enable Twilio SMS/WhatsApp
```

**Plan-gating (Tier 3):** add a per-org plan/feature flag (e.g. `organizations.plan` + a
`features()` helper) so paid drivers only fire for entitled tenants — a free/beta org never triggers a
billable call even if global creds exist. This gate is the one new cross-cutting piece Tier 3 needs;
build it as the first T3 task.

## Per-tier Definition of Done (recap)
1. New logic is a composed service; external calls behind a `null`/log-default driver.
2. Tier 1 adds **zero** paid deps and keeps the suite offline.
3. Tier 2 runs on free-tier keys with **caching + queue** to stay inside quotas; still runs with no keys.
4. Tier 3 is a **driver swap + plan gate** — no changes to `RiskEngine` or the services.
5. `.env.example`, `config/sendlock.php`, CLAUDE.md updated per driver; Pint clean; tests green.
```
