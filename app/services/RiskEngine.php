<?php

namespace App\Services;

use App\Models\Organization;
use App\Services\Ai\ContentClassifier;
use Illuminate\Support\Str;

/**
 * Decision Engine — composes the domain, content and financial intelligence
 * services into a single risk verdict for an outbound/inbound email.
 *
 * Score thresholds map to a risk level and an action decision:
 *   >= 90  CRITICAL  -> QUARANTINE          (block automatically)
 *   >= 70  HIGH      -> RECIPIENT_VERIFY     (verify recipient + approval)
 *   >= 30  MEDIUM    -> MANAGER_APPROVAL     (manager sign-off)
 *   >= 10  LOW       -> ALLOW                (send automatically)
 *   <  10  SAFE      -> ALLOW                (no signals of concern)
 *
 * Every verdict also carries a `confidence` (how corroborated the score is) and
 * decision-derived `recommendations` (what the operator should do next).
 */
class RiskEngine
{
    /**
     * @param  array{sender_email:string, subject?:?string, email_content?:?string, attachments?:string[], auth?:array, headers?:array}  $email
     */
    public static function evaluate(array $email, int $organizationId): array
    {
        $domain = self::domainFromEmail($email['sender_email']);

        $senderEmail = (string) $email['sender_email'];
        $domainResult = DomainIntelligenceService::analyze($domain, $organizationId);

        // A blocked domain short-circuits to the maximum verdict.
        if ($domainResult['signals']['is_blocked_domain'] ?? false) {
            $signals = $domainResult['signals'];

            return self::result(
                $domain,
                100,
                $domainResult['findings'],
                $signals,
                100,
                self::buildAnalysis($domain, $senderEmail, $organizationId, $signals, 100)
            );
        }

        // Full email-address trust (distinct from domain trust). A verified
        // contact is trusted even on a consumer domain and overrides the
        // untrusted-domain penalty; a look-alike of one is flagged as
        // impersonation and can never read as trusted.
        $emailResult = EmailIntelligenceService::analyze($senderEmail, $organizationId);

        if ($emailResult['status'] === 'trusted') {
            $domainResult = [
                'score' => 0,
                'findings' => ['Sender is a verified trusted contact'],
                'signals' => [
                    'is_blocked_domain' => false,
                    'is_trusted_domain' => $domainResult['signals']['is_trusted_domain'] ?? false,
                    'domain_flags' => [],
                ],
            ];
        }

        $contentResult = ContentIntelligenceService::analyze(
            $email['subject'] ?? null,
            $email['email_content'] ?? null
        );

        // AI deep pass (behind the cheap rule-based content service). Runs only
        // when an AI driver is configured AND the tenant's plan entitles it —
        // the gate that stops a paid provider firing for a non-entitled org.
        $aiResult = ['score' => 0, 'findings' => []];

        if (config('sendlock.ai.driver', 'null') !== 'null') {
            $org = Organization::find($organizationId);

            if ($org && $org->hasFeature('ai_classification')) {
                $aiResult = app(ContentClassifier::class)->classify(
                    $email['subject'] ?? null,
                    $email['email_content'] ?? null
                );
            }
        }

        $financialResult = FinancialDataService::analyze(
            $email['email_content'] ?? null,
            $domain,
            $organizationId
        );

        $authResult = EmailAuthenticationService::analyze($domain, $email['auth'] ?? []);
        $headerResult = HeaderIntelligenceService::analyze($email['headers'] ?? [], $domain);
        $urlResult = UrlInspectionService::analyze($email['email_content'] ?? null);
        $attachmentResult = AttachmentAnalysisService::analyze($email['attachments'] ?? []);
        $threatResult = ThreatIntelligenceService::analyze($domain);
        $mxResult = MxIntelligenceService::analyze($domain);
        $ageResult = DomainAgeService::analyze($domain);
        $sensitiveResult = SensitiveDataService::analyze($email['email_content'] ?? null);

        $parts = [
            $domainResult, $emailResult, $contentResult, $aiResult, $financialResult, $authResult,
            $headerResult, $urlResult, $attachmentResult, $threatResult,
            $mxResult, $ageResult, $sensitiveResult,
        ];

        $score = 0;
        $findings = [];
        foreach ($parts as $part) {
            $score += $part['score'] ?? 0;
            $findings = array_merge($findings, $part['findings'] ?? []);
        }

        $signals = array_merge(
            $domainResult['signals'],
            $emailResult['signals'],
            $authResult['signals'],
            $mxResult['signals'],
            $ageResult['signals'],
            $sensitiveResult['signals'],
            ['threat_score' => $threatResult['score']],
        );

        // Confidence reflects how many independent signal services corroborate
        // the verdict. A lone heuristic is less certain than several agreeing.
        $contributing = 0;
        foreach ($parts as $part) {
            if (($part['score'] ?? 0) > 0) {
                $contributing++;
            }
        }

        $confidence = self::confidence($score, $contributing, $domainResult['signals']['is_trusted_domain'] ?? false);

        $analysis = self::buildAnalysis($domain, $senderEmail, $organizationId, $signals, min(100, max(0, $score)));

        return self::result($domain, $score, $findings, $signals, $confidence, $analysis);
    }

    public static function domainFromEmail(string $email): string
    {
        $at = strrchr($email, '@');

        return strtolower(trim($at === false ? $email : substr($at, 1)));
    }

    private static function result(string $domain, int $score, array $findings, array $signals, int $confidence, array $analysis = []): array
    {
        $score = max(0, min(100, $score));

        [$level, $decision] = match (true) {
            $score >= 90 => ['CRITICAL', 'QUARANTINE'],
            $score >= 70 => ['HIGH', 'RECIPIENT_VERIFY'],
            $score >= 30 => ['MEDIUM', 'MANAGER_APPROVAL'],
            $score >= 10 => ['LOW', 'ALLOW'],
            default => ['SAFE', 'ALLOW'],
        };

        return [
            'domain' => $domain,
            'risk_score' => $score,
            'risk_level' => $level,
            'decision' => $decision,
            'confidence' => max(0, min(100, $confidence)),
            'recommendations' => self::recommendations($level, $decision),
            'findings' => $findings,
            'signals' => $signals,
            'analysis' => $analysis,
        ];
    }

    /**
     * Build the human-facing signal breakdown the risk-analysis page renders:
     * a labelled row per check (with an ok/warn/bad/unknown status) plus the
     * "similar trusted domain" panel derived from the lookalike detection.
     *
     * @return array{rows: array<int, array{key:string,label:string,value:string,status:string}>, similar_trusted: ?array}
     */
    private static function buildAnalysis(string $domain, string $senderEmail, int $organizationId, array $signals, int $score): array
    {
        $flags = $signals['domain_flags'] ?? [];
        $isTrusted = (bool) ($signals['is_trusted_domain'] ?? false);
        $isBlocked = (bool) ($signals['is_blocked_domain'] ?? false);
        $isTrustedRecipient = (bool) ($signals['is_trusted_recipient'] ?? false);
        $impersonates = $signals['contact_impersonation'] ?? null;
        $threatScore = (int) ($signals['threat_score'] ?? 0);

        $flagType = fn (string $type) => collect($flags)->firstWhere('type', $type);

        // --- Domain age ---
        $ageDays = $signals['domain_age_days'] ?? null;
        if ($ageDays === null) {
            $ageRow = ['Unknown', 'unknown'];
        } elseif ($ageDays <= 30) {
            $ageRow = ["{$ageDays} days (Very New)", 'bad'];
        } elseif ($ageDays <= 180) {
            $ageRow = ["{$ageDays} days", 'warn'];
        } else {
            $years = round($ageDays / 365, 1);
            $ageRow = ["{$years} yrs", 'ok'];
        }

        // --- Similarity check (typosquat / lookalike) ---
        $similar = $flagType('lookalike') ?: $flagType('typosquat');
        if ($similar && ! empty($similar['resembles'])) {
            $dist = levenshtein(self::comparable($domain), self::comparable((string) $similar['resembles']));
            $diff = max(1, $dist);
            $similarityRow = ['Very Similar ('.$diff.' char '.Str::plural('diff', $diff).')', 'bad'];
        } elseif ($isTrusted) {
            $similarityRow = ['Trusted domain', 'ok'];
        } else {
            $similarityRow = ['No close match', 'ok'];
        }

        // --- MX / SPF / DMARC ---
        $mxRow = match ($signals['mx_valid'] ?? null) {
            true => ['Valid', 'ok'],
            false => ['Missing', 'bad'],
            default => ['Not checked', 'unknown'],
        };
        $spfRow = self::authRow($signals['spf_pass'] ?? null);
        $dmarcRow = self::authRow($signals['dmarc_pass'] ?? null);

        // --- Reputation (derived) ---
        if ($impersonates) {
            $repRow = ['Low', 'bad'];
        } elseif ($isTrustedRecipient || $isTrusted) {
            $repRow = ['Trusted', 'ok'];
        } elseif ($isBlocked) {
            $repRow = ['Blocked', 'bad'];
        } elseif ($threatScore > 0) {
            $repRow = ['Malicious', 'bad'];
        } elseif ($flagType('typosquat') || $flagType('lookalike') || $flagType('homograph') || $flagType('subdomain_abuse')) {
            $repRow = ['Low', 'bad'];
        } elseif ($flagType('untrusted')) {
            $repRow = ['Unverified', 'warn'];
        } else {
            $repRow = ['Neutral', 'ok'];
        }

        // --- Previous communication ---
        $history = CommunicationHistoryService::forEmail($organizationId, $senderEmail);
        if ($history['count'] > 0) {
            $last = $history['last_at']?->format('d M Y');
            $prevRow = [$history['count'].' prior email'.($history['count'] === 1 ? '' : 's').($last ? ' (last '.$last.')' : ''), 'ok'];
        } else {
            $prevRow = ['None Found', 'bad'];
        }

        $rows = [];

        // 1) Email-provider recognition — a public provider is a valid, recognized
        // mail provider (this test PASSES), but recognition alone is not trust:
        // the engine still checks the specific address below.
        $isPublicProvider = PublicEmailProviders::is($domain);
        $rows[] = [
            'key' => 'provider',
            'label' => 'Email Provider',
            'value' => $isPublicProvider ? 'Public provider (recognized)' : 'Private / registered domain',
            'status' => 'ok',
        ];

        // 2) Trust-list membership — is this exact address (or its domain) trusted?
        // If not, the send must be verified/approved before release.
        if ($isTrustedRecipient) {
            $rows[] = ['key' => 'trusted_address', 'label' => 'In Trust List', 'value' => 'Verified contact', 'status' => 'ok'];
        } elseif ($impersonates) {
            $rows[] = ['key' => 'trusted_address', 'label' => 'In Trust List', 'value' => 'Look-alike — not verified', 'status' => 'bad'];
        } elseif ($isTrusted) {
            $rows[] = ['key' => 'trusted_address', 'label' => 'In Trust List', 'value' => 'Trusted domain', 'status' => 'ok'];
        } else {
            $rows[] = ['key' => 'trusted_address', 'label' => 'In Trust List', 'value' => 'Not found — verification required', 'status' => 'bad'];
        }

        $rows = array_merge($rows, [
            ['key' => 'domain_age', 'label' => 'Domain Age', 'value' => $ageRow[0], 'status' => $ageRow[1]],
            ['key' => 'similarity', 'label' => 'Similarity Check', 'value' => $similarityRow[0], 'status' => $similarityRow[1]],
            ['key' => 'mx', 'label' => 'MX Records', 'value' => $mxRow[0], 'status' => $mxRow[1]],
            ['key' => 'spf', 'label' => 'SPF', 'value' => $spfRow[0], 'status' => $spfRow[1]],
            ['key' => 'dmarc', 'label' => 'DMARC', 'value' => $dmarcRow[0], 'status' => $dmarcRow[1]],
            ['key' => 'reputation', 'label' => 'Domain Reputation', 'value' => $repRow[0], 'status' => $repRow[1]],
            ['key' => 'previous_communication', 'label' => 'Previous Communication', 'value' => $prevRow[0], 'status' => $prevRow[1]],
        ]);

        // Surface detected sensitive/PII data (DLP) when present — additive row.
        if (! empty($signals['sensitive_data'])) {
            $rows[] = [
                'key' => 'sensitive_data',
                'label' => 'Sensitive Data',
                'value' => 'Detected ('.count($signals['sensitive_data']).')',
                'status' => 'warn',
            ];
        }

        return [
            'rows' => $rows,
            'similar_trusted' => self::similarTrusted($senderEmail, $organizationId, $flagType('lookalike')),
            // "Address not found — did you mean …" suggestion from the verified list.
            'suggestion' => $impersonates ? ($signals['suggested_contact'] ?? $impersonates) : null,
        ];
    }

    private static function authRow(?bool $pass): array
    {
        return match ($pass) {
            true => ['Pass', 'ok'],
            false => ['Missing', 'bad'],
            default => ['Unknown', 'unknown'],
        };
    }

    /**
     * The "Similar Trusted Domain" panel — the genuine vendor domain a lookalike
     * is imitating, with the org's communication history for it.
     */
    private static function similarTrusted(string $senderEmail, int $organizationId, $lookalikeFlag): ?array
    {
        if (! $lookalikeFlag || empty($lookalikeFlag['resembles'])) {
            return null;
        }

        $trustedDomain = strtolower((string) $lookalikeFlag['resembles']);
        $localPart = str_contains($senderEmail, '@') ? strstr($senderEmail, '@', true) : 'contact';
        $history = CommunicationHistoryService::forDomain($organizationId, $trustedDomain);

        return [
            'domain' => $trustedDomain,
            'sample_email' => $localPart.'@'.$trustedDomain,
            'total' => $history['count'],
            'last_at' => $history['last_at']?->format('d M Y'),
        ];
    }

    /** Strip dots for a fairer character-diff between two domains. */
    private static function comparable(string $domain): string
    {
        return str_replace('.', '', strtolower($domain));
    }

    /**
     * How sure we are of the verdict (0–100). More independent signal services
     * agreeing raises confidence; a clean trusted email is also high-confidence.
     */
    private static function confidence(int $score, int $contributing, bool $isTrusted): int
    {
        if ($score === 0) {
            return $isTrusted ? 90 : 70;
        }

        return min(95, 55 + 12 * $contributing);
    }

    /**
     * Operator guidance derived from the decision — the "what do I do now".
     *
     * @return string[]
     */
    private static function recommendations(string $level, string $decision): array
    {
        return match ($decision) {
            'QUARANTINE' => ['Do not release this message. Quarantine it and notify your security team.'],
            'RECIPIENT_VERIFY' => [
                'Independently verify the recipient through a known, trusted channel before releasing.',
                'Confirm any banking, payment, or account changes out-of-band.',
            ],
            'MANAGER_APPROVAL' => ['Route this message to a manager for approval before it is sent.'],
            default => $level === 'SAFE'
                ? ['No action required.']
                : ['No action required — proceed with normal sending.'],
        };
    }
}
