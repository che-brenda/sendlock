<?php

namespace App\Services;

use App\Models\BlockedDomain;
use App\Models\TrustedDomain;

/**
 * Domain-layer signals for the risk engine: blocklist / trust-list membership
 * plus a battery of impersonation heuristics that need no network call —
 * homograph / IDN spoofing, algorithmic typosquatting (confusable-character and
 * edit-distance), lookalikes of trusted vendors, brand-as-subdomain abuse,
 * disposable domains, high-risk TLDs and high-entropy (random-looking) labels.
 *
 * Returns a partial signal set — score contribution, findings and structured
 * `domain_flags` — that {@see RiskEngine} composes with the other services and
 * {@see FlaggedDomainService} persists for repeat-use warnings.
 */
class DomainIntelligenceService
{
    /**
     * Brands commonly impersonated by typosquats / subdomain abuse. Compared
     * against the de-obfuscated registrable label, so this is the algorithmic
     * replacement for the old hard-coded misspelling list.
     */
    private const BRANDS = [
        'microsoft', 'office365', 'outlook', 'windows', 'amazon', 'paypal', 'google',
        'gmail', 'apple', 'icloud', 'facebook', 'instagram', 'netflix', 'linkedin',
        'dhl', 'fedex', 'ups', 'docusign', 'dropbox', 'adobe', 'coinbase', 'binance',
    ];

    /**
     * Visual character confusions used to de-obfuscate a label before comparing
     * it to a brand. Multi-character entries (rn→m, vv→w) are applied first.
     */
    private const CONFUSABLES = [
        'rn' => 'm', 'vv' => 'w', 'cl' => 'd',
        '0' => 'o', '1' => 'l', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
        '8' => 'b', '$' => 's', '@' => 'a',
    ];

    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'yopmail.com', 'trashmail.com', 'sharklasers.com', 'getnada.com', 'maildrop.cc',
    ];

    private const HIGH_RISK_TLDS = ['zip', 'mov', 'top', 'xyz', 'tk', 'gq', 'ml', 'cf', 'work', 'click', 'country', 'kim'];

    public static function analyze(string $domain, int $organizationId): array
    {
        $domain = strtolower(trim($domain));

        $isBlocked = BlockedDomain::where('organization_id', $organizationId)
            ->where('domain', $domain)
            ->where('active', true)
            ->exists();

        if ($isBlocked) {
            return [
                'score' => 100,
                'findings' => ['Domain exists in Blocked Domain Center'],
                'signals' => [
                    'is_blocked_domain' => true,
                    'is_trusted_domain' => false,
                    'domain_flags' => [],
                ],
            ];
        }

        $score = 0;
        $findings = [];

        // Impersonation / untrusted detections, recorded so a repeat use can be
        // warned on. Each entry: ['type' => ..., 'reason' => ..., 'resembles' => ?].
        $flags = [];

        $isTrusted = TrustedDomain::where('organization_id', $organizationId)
            ->where('domain', $domain)
            ->where('active', true)
            ->exists();

        if ($isTrusted) {
            // A trusted domain is taken at face value — no impersonation heuristics
            // run against it (it cannot be a typosquat of itself).
            return [
                'score' => 0,
                'findings' => ['Domain verified in Trust Center'],
                'signals' => [
                    'is_blocked_domain' => false,
                    'is_trusted_domain' => true,
                    'domain_flags' => [],
                ],
            ];
        }

        // An unrecognized counterparty is high risk on its own: +70 lands a bare
        // untrusted domain at the HIGH band before any other signal.
        $score += 70;
        $reason = 'Domain not found in Trust Center';
        $findings[] = $reason;
        $flags[] = ['type' => 'untrusted', 'reason' => $reason, 'resembles' => null];

        $label = self::registrableLabel($domain);

        // Homograph / IDN spoofing: punycode or mixed/non-ASCII scripts in the name.
        if (str_contains($domain, 'xn--') || preg_match('/[^\x00-\x7f]/', $domain)) {
            $score += 40;
            $reason = 'Domain uses non-ASCII / internationalized characters (possible homograph spoofing)';
            $findings[] = $reason;
            $flags[] = ['type' => 'homograph', 'reason' => $reason, 'resembles' => null];
        }

        // Algorithmic typosquat: de-obfuscate the label and compare to known brands
        // by exact match (after un-confusing) or small edit distance.
        if (($brand = self::typosquattedBrand($label)) !== null) {
            $score += 50;
            $reason = 'Domain resembles the well-known brand "'.$brand.'" (possible typosquat)';
            $findings[] = $reason;
            $flags[] = ['type' => 'typosquat', 'reason' => $reason, 'resembles' => $brand];
        }

        // Lookalike of one of THIS org's trusted vendor domains.
        if (($lookalike = self::lookalikeOfTrusted($domain, $organizationId)) !== null) {
            $score += 50;
            $reason = 'Domain closely resembles trusted vendor domain "'.$lookalike.'"';
            $findings[] = $reason;
            $flags[] = ['type' => 'lookalike', 'reason' => $reason, 'resembles' => $lookalike];
        }

        // Brand-as-subdomain abuse: "paypal.secure-login.com" — a brand name as a
        // sub-label of an unrelated registrable domain.
        if (($abused = self::subdomainBrandAbuse($domain)) !== null) {
            $score += 30;
            $reason = 'Brand name "'.$abused.'" used as a subdomain of an unrelated domain';
            $findings[] = $reason;
            $flags[] = ['type' => 'subdomain_abuse', 'reason' => $reason, 'resembles' => $abused];
        }

        // Disposable / throwaway mail domain.
        if (in_array($domain, self::DISPOSABLE, true)) {
            $score += 15;
            $reason = 'Disposable / throwaway email domain';
            $findings[] = $reason;
            $flags[] = ['type' => 'disposable', 'reason' => $reason, 'resembles' => null];
        }

        // High-risk / abuse-prone TLD.
        $tld = self::tld($domain);
        if (in_array($tld, self::HIGH_RISK_TLDS, true)) {
            $score += 15;
            $reason = 'Domain uses a high-risk TLD (.'.$tld.')';
            $findings[] = $reason;
            $flags[] = ['type' => 'suspicious_tld', 'reason' => $reason, 'resembles' => null];
        }

        // Random-looking label — a hallmark of throwaway / algorithmically
        // generated (DGA) domains.
        if (self::looksRandom($label)) {
            $score += 15;
            $reason = 'Domain name looks randomly generated';
            $findings[] = $reason;
            $flags[] = ['type' => 'entropy', 'reason' => $reason, 'resembles' => null];
        }

        return [
            'score' => $score,
            'findings' => $findings,
            'signals' => [
                'is_blocked_domain' => false,
                'is_trusted_domain' => false,
                'domain_flags' => $flags,
            ],
        ];
    }

    /**
     * The registrable label — the part before the public suffix (naively, the
     * second-to-last dotted segment): "paypa1.com" → "paypa1".
     */
    private static function registrableLabel(string $domain): string
    {
        $parts = explode('.', $domain);
        $count = count($parts);

        return $count >= 2 ? $parts[$count - 2] : $parts[0];
    }

    private static function tld(string $domain): string
    {
        $parts = explode('.', $domain);

        return strtolower(end($parts));
    }

    /**
     * De-obfuscate a label by collapsing visual confusables, then match it to a
     * known brand — either exactly (after un-confusing) or within 1–2 edits.
     */
    private static function typosquattedBrand(string $label): ?string
    {
        $deconfused = strtr($label, self::CONFUSABLES);

        foreach (self::BRANDS as $brand) {
            // A confusable-obfuscated spelling of the brand (paypa1 -> paypal),
            // where the raw label was NOT already the brand.
            if ($deconfused === $brand && $label !== $brand) {
                return $brand;
            }

            // A near-miss spelling (gooogle, micrsoft) but not the brand itself.
            $distance = levenshtein($label, $brand);
            if ($distance > 0 && $distance <= 2 && abs(strlen($label) - strlen($brand)) <= 2) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Find a trusted domain that the given domain is a near-match for
     * (1–2 character edits away), which is the classic lookalike attack.
     */
    private static function lookalikeOfTrusted(string $domain, int $organizationId): ?string
    {
        $trusted = TrustedDomain::where('organization_id', $organizationId)
            ->where('active', true)
            ->pluck('domain');

        foreach ($trusted as $trustedDomain) {
            $trustedDomain = strtolower($trustedDomain);

            if ($trustedDomain === $domain) {
                continue;
            }

            $distance = levenshtein($domain, $trustedDomain);

            if ($distance > 0 && $distance <= 2) {
                return $trustedDomain;
            }
        }

        return null;
    }

    /**
     * A brand name appearing as a sub-label of a different registrable domain,
     * e.g. "paypal.secure-login.com" (registrable domain is secure-login.com).
     */
    private static function subdomainBrandAbuse(string $domain): ?string
    {
        $parts = explode('.', $domain);

        // Needs at least subdomain + sld + tld.
        if (count($parts) < 3) {
            return null;
        }

        $sld = self::registrableLabel($domain);

        // Inspect every label except the registrable one and the TLD.
        $subLabels = array_slice($parts, 0, count($parts) - 2);

        foreach ($subLabels as $sub) {
            if (in_array($sub, self::BRANDS, true) && $sld !== $sub) {
                return $sub;
            }
        }

        return null;
    }

    /**
     * Heuristic for "random-looking" labels (DGA / throwaway domains).
     * Deliberately conservative: a long label that is either near-vowelless
     * (unpronounceable) or heavily numeric. Ordinary multi-word vendor names —
     * which keep a healthy vowel ratio and few digits — do not trip it.
     */
    private static function looksRandom(string $label): bool
    {
        $chars = preg_replace('/[^a-z0-9]/', '', $label);
        $len = strlen($chars);

        if ($len < 10) {
            return false;
        }

        $vowels = preg_match_all('/[aeiou]/', $chars);
        $digits = preg_match_all('/[0-9]/', $chars);

        $vowelRatio = $vowels / $len;
        $digitRatio = $digits / $len;

        return $vowelRatio < 0.26 || $digitRatio >= 0.4;
    }
}
