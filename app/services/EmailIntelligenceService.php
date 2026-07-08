<?php

namespace App\Services;

use App\Models\VerifiedRecipient;

/**
 * Full email-address trust — distinct from domain trust. Domain trust says
 * "anyone @vendor.com is fine"; this verifies the WHOLE address against the
 * org's verified contacts and catches when a trusted address is altered by even
 * a single character in either the local part or the domain (e.g. a verified
 * `chebrenda93@gmail.com` must not make a spoofed `chebrendn93@gmail.com` look
 * trusted). Returns one of three statuses:
 *
 *   trusted       — an exact verified contact (overrides the untrusted-domain
 *                   penalty; a consumer-domain contact is still trusted).
 *   impersonation — a look-alike of a verified contact (one/two edits or a
 *                   confusable-character swap) — high risk, never trusted.
 *   none          — no relationship to any verified contact (defer to domain).
 */
class EmailIntelligenceService
{
    /** Visual character confusions used to de-obfuscate a label before comparing. */
    private const CONFUSABLES = [
        'rn' => 'm', 'vv' => 'w', 'cl' => 'd',
        '0' => 'o', '1' => 'l', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', '$' => 's',
    ];

    /** Impersonation is any near-match within this many single-character edits. */
    private const MAX_EDITS = 2;

    private const IMPERSONATION_SCORE = 75;

    public static function analyze(string $senderEmail, int $organizationId): array
    {
        $email = strtolower(trim($senderEmail));

        if ($email === '' || ! str_contains($email, '@')) {
            return self::none();
        }

        $verified = VerifiedRecipient::where('organization_id', $organizationId)
            ->where('verified', true)
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim($e)))
            ->filter()
            ->unique();

        // Exact verified contact → trusted, regardless of domain.
        if ($verified->contains($email)) {
            return [
                'status' => 'trusted',
                'score' => 0,
                'findings' => ['Sender is a verified trusted contact'],
                'signals' => ['is_trusted_recipient' => true, 'contact_impersonation' => null],
                'resembles' => $email,
            ];
        }

        // Not an exact contact: find the CLOSEST verified contact within the
        // look-alike threshold and offer it as a "did you mean" suggestion. (An
        // exact match was handled above, so two legitimately-distinct verified
        // contacts like jone@ and joan@ each resolve to themselves and are never
        // flagged against each other.)
        $suggestion = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($verified as $trusted) {
            if (self::isLookalike($email, $trusted)) {
                $distance = levenshtein($email, $trusted);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $suggestion = $trusted;
                }
            }
        }

        if ($suggestion !== null) {
            return [
                'status' => 'impersonation',
                'score' => self::IMPERSONATION_SCORE,
                'findings' => ['Address not found in your verified contacts — did you mean "'.$suggestion.'"?'],
                'signals' => [
                    'is_trusted_recipient' => false,
                    'contact_impersonation' => $suggestion,
                    'suggested_contact' => $suggestion,
                ],
                'resembles' => $suggestion,
            ];
        }

        return self::none();
    }

    private static function none(): array
    {
        return [
            'status' => 'none',
            'score' => 0,
            'findings' => [],
            'signals' => ['is_trusted_recipient' => false, 'contact_impersonation' => null, 'suggested_contact' => null],
            'resembles' => null,
        ];
    }

    /**
     * Is $candidate a deceptive near-match of the trusted address $trusted?
     * Checks the whole address, and — holding one side constant — the local part
     * and the domain independently, by both edit distance and confusable swaps.
     */
    private static function isLookalike(string $candidate, string $trusted): bool
    {
        if ($candidate === $trusted) {
            return false;
        }

        // Whole-address: a couple of edits, or the same string once de-obfuscated.
        if (self::near($candidate, $trusted)) {
            return true;
        }

        [$cLocal, $cDomain] = self::split($candidate);
        [$tLocal, $tDomain] = self::split($trusted);

        // Same domain, tampered local part (chebrenda93 -> chebrendn93).
        if ($cDomain === $tDomain && $cLocal !== $tLocal && self::near($cLocal, $tLocal)) {
            return true;
        }

        // Same local part, tampered domain (…@gmail.com -> …@gmial.com).
        if ($cLocal === $tLocal && $cDomain !== $tDomain && self::near($cDomain, $tDomain)) {
            return true;
        }

        return false;
    }

    /** Near = within MAX_EDITS Levenshtein, or identical after de-obfuscation. */
    private static function near(string $a, string $b): bool
    {
        if ($a === $b) {
            return false;
        }

        $distance = levenshtein($a, $b);

        if ($distance > 0 && $distance <= self::MAX_EDITS) {
            return true;
        }

        return self::deconfuse($a) === self::deconfuse($b);
    }

    private static function deconfuse(string $value): string
    {
        return strtr($value, self::CONFUSABLES);
    }

    /**
     * @return array{0:string, 1:string} [localPart, domain]
     */
    private static function split(string $email): array
    {
        $at = strrpos($email, '@');

        return [substr($email, 0, $at), substr($email, $at + 1)];
    }
}
