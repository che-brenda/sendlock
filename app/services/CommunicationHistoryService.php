<?php

namespace App\Services;

use App\Models\Communication;
use Illuminate\Support\Carbon;

/**
 * Communication Relationship Analysis / Recipient Intelligence.
 *
 * Tracks, per organization, which counterparts (email addresses / domains) an
 * org has communicated with and how often. This is the offline data source for
 * the "Previous Communication" signal and the trusted-domain history shown on
 * the risk-analysis page — a brand-new sender the org has never dealt with is a
 * weaker trust signal than an address seen dozens of times.
 */
class CommunicationHistoryService
{
    /**
     * Record one interaction with a counterpart address (idempotently
     * aggregated). Call this AFTER evaluating a scan so the current message is
     * not counted as its own "previous communication".
     */
    public static function record(int $organizationId, string $email): void
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return;
        }

        $domain = RiskEngine::domainFromEmail($email);
        $now = Carbon::now();

        $row = Communication::firstOrNew([
            'organization_id' => $organizationId,
            'counterpart_email' => $email,
        ]);

        $row->counterpart_domain = $domain;
        $row->occurrences = (int) $row->occurrences + 1;
        $row->first_seen_at = $row->first_seen_at ?? $now;
        $row->last_seen_at = $now;
        $row->save();
    }

    /**
     * Prior-communication summary for a single address.
     *
     * @return array{count:int, last_at:?Carbon}
     */
    public static function forEmail(int $organizationId, string $email): array
    {
        $row = Communication::where('organization_id', $organizationId)
            ->where('counterpart_email', strtolower(trim($email)))
            ->first();

        return [
            'count' => $row ? (int) $row->occurrences : 0,
            'last_at' => $row?->last_seen_at,
        ];
    }

    /**
     * Prior-communication summary aggregated across every address at a domain.
     * Used for the "Similar Trusted Domain" panel (total emails + last email).
     *
     * @return array{count:int, last_at:?Carbon}
     */
    public static function forDomain(int $organizationId, string $domain): array
    {
        $rows = Communication::where('organization_id', $organizationId)
            ->where('counterpart_domain', strtolower(trim($domain)))
            ->get();

        return [
            'count' => (int) $rows->sum('occurrences'),
            'last_at' => $rows->max('last_seen_at'),
        ];
    }
}
