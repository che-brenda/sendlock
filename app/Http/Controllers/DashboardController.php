<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Services\RiskReasonStats;

/**
 * Security dashboard. A Super Admin sees platform-wide totals plus every head
 * organization and its sub-org count. Everyone else sees their own organization
 * **and its sub-organizations** — totals and recent activity aggregate across the
 * org's subtree (`descendantIds()` = self + sub-orgs), so a head organization
 * sees everything happening beneath it. Drill-down into a sub-org is read-only
 * and limited to Head Organization Admins (and Super Admins).
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            $risk = RiskReasonStats::forOrganizations(Organization::query()->pluck('id')->all());

            return view('dashboard', [
                'organizations' => Organization::count(),
                'users' => User::count(),
                'departments' => Department::count(),
                'activeUsers' => User::where('status', true)->count(),
                'inactiveUsers' => User::where('status', false)->count(),
                'recentLogs' => AuditLog::visibleTo($user)->latest()->take(10)->get(),
                'riskReasons' => $risk['segments'],
                'riskTotal' => $risk['total'],
                'subOrganizations' => collect(),
                'headOrganizations' => Organization::where('type', 'head')
                    ->withCount('children')
                    ->latest()
                    ->take(25)
                    ->get(),
                'aggregatesSubOrgs' => false,
                'canDrillDown' => false,
                'currentOrg' => null,   // platform-wide
            ]);
        }

        $org = $user->organization;
        $org?->load('parent');                      // for the header breadcrumb
        $ids = $org ? $org->descendantIds() : [];   // self + sub-orgs (2 levels)

        // The sub-organization section is only meaningful for a head organization.
        $subOrganizations = ($org && $org->isHead())
            ? Organization::where('parent_id', $org->id)
                ->withCount(['users', 'departments', 'emailScans'])
                ->latest()
                ->get()
            : collect();

        $risk = RiskReasonStats::forOrganizations($ids);

        return view('dashboard', [
            'organizations' => count($ids),
            'users' => User::whereIn('organization_id', $ids)->count(),
            'departments' => Department::whereIn('organization_id', $ids)->count(),
            'activeUsers' => User::whereIn('organization_id', $ids)->where('status', true)->count(),
            'inactiveUsers' => User::whereIn('organization_id', $ids)->where('status', false)->count(),
            'recentLogs' => AuditLog::visibleTo($user)->latest()->take(10)->get(),
            'riskReasons' => $risk['segments'],
            'riskTotal' => $risk['total'],
            'subOrganizations' => $subOrganizations,
            'headOrganizations' => collect(),
            'aggregatesSubOrgs' => $subOrganizations->isNotEmpty(),
            'canDrillDown' => $user->canManageSubOrganizations(),
            'currentOrg' => $org,
        ]);
    }
}
