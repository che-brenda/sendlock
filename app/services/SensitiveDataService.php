<?php

namespace App\Services;

/**
 * Sensitive-information detection (PII) + data-loss-prevention signal. Scans
 * content for confidential/regulated data — card numbers (Luhn-validated), bank
 * identifiers, government IDs and explicit confidentiality markers — so the
 * platform can flag when such data is present in a message. Detection only: it
 * reports (findings + a `sensitive_data` signal) and does not itself change the
 * risk score, so it can be surfaced on outbound review without distorting the
 * threat verdict.
 */
class SensitiveDataService
{
    public static function analyze(?string $content): array
    {
        $text = (string) $content;

        if (trim($text) === '') {
            return ['score' => 0, 'findings' => [], 'signals' => ['sensitive_data' => []]];
        }

        $types = [];

        if (self::hasCreditCard($text)) {
            $types['payment_card'] = 'Payment card number';
        }

        if (preg_match('/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/', strtoupper($text))) {
            $types['bank_iban'] = 'Bank account (IBAN)';
        }

        if (preg_match('/\b[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?\b/', strtoupper($text)) && stripos($text, 'swift') !== false) {
            $types['bank_swift'] = 'Bank SWIFT/BIC code';
        }

        if (preg_match('/\b\d{3}-\d{2}-\d{4}\b/', $text)) {
            $types['gov_id'] = 'Government ID (SSN format)';
        }

        if (preg_match('/\b(confidential|proprietary|do not distribute|internal use only)\b/i', $text)) {
            $types['confidential_marker'] = 'Confidentiality marker';
        }

        $findings = [];
        foreach ($types as $label) {
            $findings[] = 'Sensitive data present: '.$label;
        }

        return [
            'score' => 0,
            'findings' => $findings,
            'signals' => ['sensitive_data' => array_keys($types)],
        ];
    }

    /**
     * Any 13–19 digit run (spaces/dashes allowed) that passes the Luhn checksum.
     */
    private static function hasCreditCard(string $text): bool
    {
        if (! preg_match_all('/\b(?:\d[ -]?){13,19}\b/', $text, $matches)) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);

            if (strlen($digits) >= 13 && strlen($digits) <= 19 && self::luhn($digits)) {
                return true;
            }
        }

        return false;
    }

    private static function luhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];

            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }

            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
