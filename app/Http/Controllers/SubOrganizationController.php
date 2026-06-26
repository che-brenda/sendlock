<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use App\Helpers\AuditLogger;

/**
 * Lets a Head Organization Admin manage the sub-organizations beneath their
 * own head organization. All queries are scoped to children of the current
 * user's organization so one head org can never see another's tree.
 */
class SubOrganizationController extends Controller
{
    private function headOrganization(): Organization
    {
        $org = auth()->user()->organization;

        abort_if($org === null, 403, 'No organization context.');

        // Only a head organization may own sub-organizations.
        abort_unless($org->isHead(), 403, 'Only a head organization can manage sub-organizations.');

        return $org;
    }

    public function index()
    {
        $head = $this->headOrganization();

        $subOrganizations = Organization::where('parent_id', $head->id)
            ->withCount('users', 'departments')
            ->latest()
            ->get();

        return view('sub-organizations.index', compact('subOrganizations', 'head'));
    }

    public function create()
    {
        $head = $this->headOrganization();

        return view('sub-organizations.create', compact('head'));
    }

    public function store(Request $request)
    {
        $head = $this->headOrganization();

        $validated = $request->validate([
            'organization_name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $subOrganization = Organization::create([
            'parent_id' => $head->id,
            'type' => 'sub',
            'organization_name' => $validated['organization_name'],
            'industry' => $validated['industry'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'subscription_plan' => $head->subscription_plan,
            'status' => true,
        ]);

        AuditLogger::log(
            'CREATE',
            'SUB_ORGANIZATION',
            $subOrganization->id,
            'Created sub-organization ' . $subOrganization->organization_name
        );

        return redirect()
            ->route('sub-organizations.index')
            ->with('success', 'Sub-organization created successfully.');
    }

    public function show(Organization $subOrganization)
    {
        $head = $this->headOrganization();

        // Scope: the record must be a child of the current head organization.
        abort_unless((int) $subOrganization->parent_id === (int) $head->id, 403);

        $subOrganization->loadCount('users', 'departments');

        return view('sub-organizations.show', compact('subOrganization', 'head'));
    }
}
