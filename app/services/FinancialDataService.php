<?php

namespace App\Services;

use App\Models\VendorBankAccount;

/**
 * Financial Data Comparison Engine.
 *
 * Extracts banking details (IBAN, SWIFT/BIC, account numbers) from email
 * content and compares them against the known-good {@see VendorBankAccount}
 * records for the counterparty domain. A mismatch is the strongest single
 * indicator of payment-diversion / bank-change fraud.
 */
class FinancialDataService
{
    public static function analyze(?string $content, string $domain, int $organizationId): array
    {
        $content = (string) $content;

        if (trim($content) === '') {
            return ['score' => 0, 'findings' => []];
        }

        $extracted = self::extract($content);

        if (empty($extracted['ibans']) && empty($extracted['swifts']) && empty($extracted['accounts'])) {
            return ['score' => 0, 'findings' => []];
        }

        $score = 20;
        $findings = ['Financial data detected in email content'];

        $known = VendorBankAccount::where('organization_id', $organizationId)
            ->where('vendor_domain', strtolower(trim($domain)))
            ->get();

        if ($known->isEmpty()) {
            // We have no baseline for this vendor — financial data from an
            // unknown counterparty is itself a moderate risk.
            $score += 15;
            $findings[] = 'No known banking baseline for this vendor to compare against';

            return ['score' => $score, 'findings' => $findings];
        }

        $mismatch = self::detectMismatch($extracted, $known);

        if ($mismatch) {
            $score += 60;
            $findings[] = 'Banking details differ from the known account on file for this vendor — independent verification required';
        } else {
            $findings[] = 'Banking details match the vendor\'s known account';
        }

        return ['score' => $score, 'findings' => $findings];
    }

    /**
     * @return array{ibans: string[], swifts: string[], accounts: string[]}
     */
    public static function extract(string $content): array
    {
        $normalized = strtoupper($content);

        preg_match_all('/\b[A-Z]{2}\d{2}[A-Z0-9]{10,30}\b/', str_replace(' ', '', $normalized), $ibans);

        // SWIFT/BIC only when explicitly labelled, so ordinary 8-letter words
        // (e.g. "CHECKING") are not mistaken for bank codes.
        preg_match_all('/\b(?:SWIFT|BIC)\b[:\s]*([A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?)\b/', $normalized, $swifts);

        preg_match_all('/\b\d{8,18}\b/', $content, $accounts);

        return [
            'ibans' => array_values(array_unique($ibans[0] ?? [])),
            'swifts' => array_values(array_unique($swifts[1] ?? [])),
            'accounts' => array_values(array_unique($accounts[0] ?? [])),
        ];
    }

    /**
     * True when extracted details do not match any known record for the vendor.
     */
    private static function detectMismatch(array $extracted, $known): bool
    {
        $knownAccounts = $known->pluck('account_number')->filter()->map(fn ($v) => self::digits($v))->all();
        $knownIbans = $known->pluck('iban')->filter()->map(fn ($v) => strtoupper(str_replace(' ', '', $v)))->all();

        foreach ($extracted['ibans'] as $iban) {
            if (! in_array($iban, $knownIbans, true)) {
                return true;
            }
        }

        foreach ($extracted['accounts'] as $account) {
            $account = self::digits($account);

            // Ignore values that are actually part of a known IBAN.
            $insideIban = false;
            foreach ($knownIbans as $iban) {
                if (str_contains(self::digits($iban), $account)) {
                    $insideIban = true;
                    break;
                }
            }

            if ($insideIban) {
                continue;
            }

            if (! in_array($account, $knownAccounts, true)) {
                return true;
            }
        }

        return false;
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
