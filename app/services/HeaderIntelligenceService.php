<?php

namespace App\Services;

/**
 * Header-layer signals: the classic Business Email Compromise tells that live in
 * an inbound message's headers rather than its body. None of these need a network
 * call — they compare the visible "From" against the addresses an attacker can't
 * easily align (Reply-To, Return-Path) and against the display name itself.
 *
 * Returns a partial signal set — score contribution and findings — that
 * {@see RiskEngine} composes with the other intelligence services. Absence of a
 * header is treated as "no signal" (score 0), never as a failure, mirroring
 * {@see EmailAuthenticationService}.
 *
 * @see analyze() accepts the headers supplied with a scan; the authoritative
 *      sender domain is passed in (derived from the envelope "From" address).
 */
class HeaderIntelligenceService
{
    private const MAX_SCORE = 50;

    /**
     * @param  array{from_name?:?string, reply_to?:?string, return_path?:?string}  $headers
     */
    public static function analyze(array $headers, string $senderDomain): array
    {
        $senderDomain = self::domainOf($senderDomain);

        $score = 0;
        $findings = [];

        // Reply-To pointing at a different domain than the From address is the
        // single most common BEC redirection trick: the victim's reply silently
        // goes to the attacker.
        $replyToDomain = self::domainOf((string) ($headers['reply_to'] ?? ''));
        if ($replyToDomain !== null && $senderDomain !== null && $replyToDomain !== $senderDomain) {
            $score += 25;
            $findings[] = 'Reply-To domain "'.$replyToDomain.'" differs from the sender domain "'.$senderDomain.'"';
        }

        // Return-Path / envelope-from mismatch — a weaker but corroborating signal
        // of spoofing or a forwarded/relayed impersonation.
        $returnPathDomain = self::domainOf((string) ($headers['return_path'] ?? ''));
        if ($returnPathDomain !== null && $senderDomain !== null && $returnPathDomain !== $senderDomain) {
            $score += 15;
            $findings[] = 'Return-Path domain "'.$returnPathDomain.'" differs from the sender domain "'.$senderDomain.'"';
        }

        // Display-name spoofing: the "friendly" name embeds an email address whose
        // domain is not the real sender (e.g. From: "ceo@company.com <attacker@evil.com>").
        $fromName = (string) ($headers['from_name'] ?? '');
        if (preg_match('/[\w.+-]+@([\w.-]+\.[a-z]{2,})/i', $fromName, $m)) {
            $nameDomain = self::domainOf($m[1]);
            if ($nameDomain !== null && $senderDomain !== null && $nameDomain !== $senderDomain) {
                $score += 30;
                $findings[] = 'Display name impersonates a different address at "'.$nameDomain.'"';
            }
        }

        return [
            'score' => min($score, self::MAX_SCORE),
            'findings' => $findings,
        ];
    }

    /**
     * Reduce an email address, URL or bare domain to its registrable-ish host
     * (lowercased, scheme/path/mailbox stripped). Returns null when empty.
     */
    private static function domainOf(string $value): ?string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        // If it's an email address, take the part after the last "@".
        $at = strrchr($value, '@');
        if ($at !== false) {
            $value = substr($at, 1);
        }

        $value = preg_replace('~^https?://~', '', $value);
        $value = preg_replace('~[/?#].*$~', '', $value);
        $value = preg_replace('~^www\.~', '', $value);
        $value = trim($value, " <>\"'");

        return $value === '' ? null : $value;
    }
}
