<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflow;
use Illuminate\Http\Request;

/**
 * Approvals queue: managers and above approve or reject requests that are
 * awaiting sign-off. Tenant-scoped; approval is gated by role.
 */
class ApprovalController extends Controller
{
    private const APPROVER_ROLES = [
        'Super Admin',
        'Head Organization Admin',
        'Organization Admin',
        'Manager',
    ];

    public function index()
    {
        $this->authorizeApprover();

        $requests = ApprovalRequest::where('organization_id', auth()->user()->organization_id)
            ->where('status', ApprovalRequest::STATUS_PENDING_APPROVAL)
            ->with('user')
            ->latest()
            ->get();

        return view('approvals.index', compact('requests'));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest, ApprovalWorkflow $workflow)
    {
        $this->authorizeApprover();
        $this->authorizeTenant($approvalRequest);
        $this->authorizeStage($approvalRequest);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);

        $workflow->approve($approvalRequest, auth()->id(), $validated['notes'] ?? null);

        return back()->with('success', 'Request approved and ' . strtolower($approvalRequest->status) . '.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest, ApprovalWorkflow $workflow)
    {
        $this->authorizeApprover();
        $this->authorizeTenant($approvalRequest);
        $this->authorizeStage($approvalRequest);

        $validated = $request->validate(['notes' => 'nullable|string|max:1000']);

        $workflow->reject($approvalRequest, auth()->id(), $validated['notes'] ?? null);

        return back()->with('success', 'Request rejected.');
    }

    private function authorizeApprover(): void
    {
        abort_unless(auth()->user()->hasAnyRole(self::APPROVER_ROLES), 403);
    }

    private function authorizeTenant(ApprovalRequest $approvalRequest): void
    {
        abort_unless(
            $approvalRequest->organization_id === auth()->user()->organization_id,
            403
        );
    }

    private function authorizeStage(ApprovalRequest $approvalRequest): void
    {
        abort_unless(
            $approvalRequest->status === ApprovalRequest::STATUS_PENDING_APPROVAL,
            422,
            'This request is not awaiting approval.'
        );
    }
}
