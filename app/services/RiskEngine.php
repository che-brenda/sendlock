<?php

namespace App\Services;

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

        $domainResult = DomainIntelligenceService::analyze($domain, $organizationId);

        // A blocked domain short-circuits to the maximum verdict.
        if ($domainResult['signals']['is_blocked_domain'] ?? false) {
            return self::result(
                $domain,
                100,
                $domainResult['findings'],
                $domainResult['signals'],
                100
            );
        }

        $contentResult = ContentIntelligenceService::analyze(
            $email['subject'] ?? null,
            $email['email_content'] ?? null
        );

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

        $score = $domainResult['score']
            + $contentResult['score']
            + $financialResult['score']
            + $authResult['score']
            + $headerResult['score']
            + $urlResult['score']
            + $attachmentResult['score']
            + $threatResult['score'];

        $findings = array_merge(
            $domainResult['findings'],
            $contentResult['findings'],
            $financialResult['findings'],
            $authResult['findings'],
            $headerResult['findings'],
            $urlResult['findings'],
            $attachmentResult['findings'],
            $threatResult['findings']
        );

        $signals = array_merge($domainResult['signals'], $authResult['signals']);

        // Confidence reflects how many independent signal services corroborate
        // the verdict. A lone heuristic is less certain than several agreeing.
        $contributing = 0;
        foreach ([$domainResult, $contentResult, $financialResult, $authResult, $headerResult, $urlResult, $attachmentResult, $threatResult] as $part) {
            if (($part['score'] ?? 0) > 0) {
                $contributing++;
            }
        }

        $confidence = self::confidence($score, $contributing, $domainResult['signals']['is_trusted_domain'] ?? false);

        return self::result($domain, $score, $findings, $signals, $confidence);
    }

    public static function domainFromEmail(string $email): string
    {
        $at = strrchr($email, '@');

        return strtolower(trim($at === false ? $email : substr($at, 1)));
    }

    private static function result(string $domain, int $score, array $findings, array $signals, int $confidence): array
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
        ];
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
