<?php

namespace App\Services;

use App\Models\EmailScan;

/**
 * Derives the "Top Risk Reasons" breakdown for an organization (or its tree)
 * from real EmailScan history — the data behind the dashboard's animated donut.
 *
 * Each risky scan (MEDIUM+) is assigned a single PRIMARY reason by priority (so
 * the segments are mutually exclusive and read as "the main thing that flagged
 * it"), tallied, and returned ordered by frequency with a canonical colour.
 */
class RiskReasonStats
{
    /** Canonical reason → colour (kept in sync with the chart legend). */
    public const COLORS = [
        'Similar Domain' => '#6366f1',
        'New Domain' => '#ef4444',
        'No Prior Communication' => '#22c55e',
        'Low Reputation' => '#06b6d4',
        'Impersonation' => '#f59e0b',
        'Others' => '#94a3b8',
    ];

    private const RISKY_LEVELS = ['MEDIUM', 'HIGH', 'CRITICAL'];

    /**
     * @param  int[]  $organizationIds
     * @return array{segments: array<int, array{label:string, value:int, color:string}>, total:int, flagged:int}
     */
    public static function forOrganizations(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return ['segments' => [], 'total' => 0, 'flagged' => 0];
        }

        $scans = EmailScan::whereIn('organization_id', $organizationIds)
            ->get(['risk_level', 'analysis']);

        $counts = [];

        foreach ($scans as $scan) {
            if (! in_array($scan->risk_level, self::RISKY_LEVELS, true)) {
                continue;
            }

            $reason = self::primaryReason($scan);
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        arsort($counts);

        $segments = [];
        foreach ($counts as $reason => $count) {
            $segments[] = [
                'label' => $reason,
                'value' => $count,
                'color' => self::COLORS[$reason] ?? self::COLORS['Others'],
            ];
        }

        return [
            'segments' => $segments,
            'total' => $scans->count(),
            'flagged' => array_sum($counts),
        ];
    }

    /**
     * The single dominant reason a scan was flagged, from its persisted analysis
     * breakdown. Baseline "untrusted"/"unverified" states are intentionally not
     * reasons — only the distinctive signals are.
     */
    private static function primaryReason(EmailScan $scan): string
    {
        $rows = collect($scan->analysis['rows'] ?? []);

        $status = fn (string $key) => optional($rows->firstWhere('key', $key))['status'] ?? null;
        $value = fn (string $key) => (string) (optional($rows->firstWhere('key', $key))['value'] ?? '');

        return match (true) {
            str_contains($value('trusted_address'), 'Look-alike') => 'Impersonation',
            $status('similarity') === 'bad' => 'Similar Domain',
            $status('domain_age') === 'bad' => 'New Domain',
            $status('reputation') === 'bad' => 'Low Reputation',
            $status('previous_communication') === 'bad' => 'No Prior Communication',
            default => 'Others',
        };
    }
}
