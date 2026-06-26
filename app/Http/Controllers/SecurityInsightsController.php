<?php

namespace App\Http\Controllers;

use App\Models\EmailScan;

/**
 * Read-only security insight pages derived from scan history. Tenant-scoped;
 * Super Admins see all organizations.
 */
class SecurityInsightsController extends Controller
{
    public function threatOverview()
    {
        $query = $this->scopedScans();

        $byLevel = (clone $query)
            ->selectRaw('risk_level, count(*) as total')
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        $levels = ['SAFE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
        $counts = collect($levels)->mapWithKeys(fn ($l) => [$l => (int) ($byLevel[$l] ?? 0)]);

        $totalScans = (clone $query)->count();

        $highRisk = (clone $query)
            ->whereIn('risk_level', ['HIGH', 'CRITICAL'])
            ->latest()
            ->take(10)
            ->get();

        return view('threat-overview.index', compact('counts', 'totalScans', 'highRisk'));
    }

    public function blockedAttempts()
    {
        $blocked = $this->scopedScans()
            ->where(function ($q) {
                $q->where('decision', 'QUARANTINE')
                    ->orWhere('is_blocked_domain', true)
                    ->orWhere('risk_level', 'CRITICAL');
            })
            ->latest()
            ->paginate(20);

        return view('blocked-attempts.index', compact('blocked'));
    }

    /**
     * Scans visible to the current user: their organization, or all for Super Admin.
     */
    private function scopedScans()
    {
        $query = EmailScan::query();

        if (! auth()->user()->isSuperAdmin()) {
            $query->where('organization_id', auth()->user()->organization_id);
        }

        return $query;
    }
}
