<?php

namespace App\Services;

/**
 * URL inspection: pulls links out of email content and flags the classic
 * phishing tells — IP-literal links, high-abuse TLDs, URL shorteners, and
 * markdown anchor/href mismatch (display domain != actual domain).
 */
class UrlInspectionService
{
    private const SUSPICIOUS_TLDS = ['ru', 'top', 'xyz', 'zip', 'mov', 'cn', 'tk', 'gq', 'work', 'click'];

    private const SHORTENERS = ['bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'is.gd', 'buff.ly', 'cutt.ly'];

    private const MAX_SCORE = 50;

    public static function analyze(?string $content): array
    {
        $content = (string) $content;

        if (trim($content) === '') {
            return ['score' => 0, 'findings' => []];
        }

        $score = 0;
        $findings = [];

        // Markdown / HTML anchor mismatch: [Display](http://actual) where the
        // visible text names a different domain than the link target.
        if (preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/i', $content, $anchors, PREG_SET_ORDER)) {
            foreach ($anchors as $anchor) {
                $displayDomain = self::domainOf($anchor[1]);
                $hrefDomain = self::domainOf($anchor[2]);

                if ($displayDomain !== null && $hrefDomain !== null && $displayDomain !== $hrefDomain) {
                    $score += 35;
                    $findings[] = 'Link text "' . $displayDomain . '" points to a different domain "' . $hrefDomain . '"';
                }
            }
        }

        preg_match_all('/https?:\/\/[^\s\)\]"\'<>]+/i', $content, $matches);

        foreach (array_unique($matches[0] ?? []) as $url) {
            $host = self::domainOf($url);

            if ($host === null) {
                continue;
            }

            if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
                $score += 25;
                $findings[] = 'Link uses a raw IP address: ' . $host;
                continue;
            }

            if (in_array($host, self::SHORTENERS, true)) {
                $score += 10;
                $findings[] = 'Link uses a URL shortener: ' . $host;
            }

            $tld = strtolower((string) substr(strrchr($host, '.') ?: '', 1));
            if (in_array($tld, self::SUSPICIOUS_TLDS, true)) {
                $score += 20;
                $findings[] = 'Link uses a high-risk TLD: .' . $tld;
            }
        }

        return ['score' => min($score, self::MAX_SCORE), 'findings' => $findings];
    }

    private static function domainOf(string $value): ?string
    {
        $value = trim(strtolower($value));
        $value = preg_replace('~^https?://~', '', $value);
        $value = preg_replace('~[/?#].*$~', '', $value);
        $value = preg_replace('~^www\.~', '', $value);

        return $value === '' ? null : $value;
    }
}
