<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflow;
use App\Services\Verification\VerificationService;
use App\Helpers\AuditLogger;
use Illuminate\Http\Request;

/**
 * Recipient Verification Center: issues and checks verification codes for
 * requests awaiting recipient confirmation. All actions are tenant-scoped.
 */
class RecipientVerificationController extends Controller
{
    public function index()
    {
        $requests = ApprovalRequest::where('organization_id', auth()->user()->organization_id)
            ->where('status', ApprovalRequest::STATUS_PENDING_VERIFICATION)
            ->with('verifications')
            ->latest()
            ->get();

        return view('recipient-verification.index', compact('requests'));
    }

    public function send(Request $request, ApprovalRequest $approvalRequest, VerificationService $service)
    {
        $this->authorizeTenant($approvalRequest);
        $this->authorizeStage($approvalRequest);

        $validated = $request->validate([
            'channel' => 'required|in:sms,whatsapp,email',
            'phone' => 'nullable|string|max:50',
        ]);

        try {
            $service->issue($approvalRequest, $validated['channel'], $validated['phone'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['channel' => 'Could not send the verification code. Please try again or use another channel.']);
        }

        AuditLogger::log(
            'VERIFICATION_SENT',
            'APPROVAL_REQUEST',
            $approvalRequest->id,
            'Verification code sent via ' . $validated['channel'] . ' for ' . $approvalRequest->recipient_email
        );

        return back()->with('success', 'Verification code sent via ' . $validated['channel'] . '.');
    }

    public function verify(Request $request, ApprovalRequest $approvalRequest, VerificationService $service, ApprovalWorkflow $workflow)
    {
        $this->authorizeTenant($approvalRequest);
        $this->authorizeStage($approvalRequest);

        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        if (! $service->check($approvalRequest, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        $workflow->markRecipientVerified($approvalRequest);

        AuditLogger::log(
            'VERIFICATION_CONFIRMED',
            'APPROVAL_REQUEST',
            $approvalRequest->id,
            'Recipient verified for ' . $approvalRequest->recipient_email
        );

        return back()->with('success', 'Recipient verified. Request advanced to ' . $approvalRequest->status . '.');
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
            $approvalRequest->status === ApprovalRequest::STATUS_PENDING_VERIFICATION,
            422,
            'This request is not awaiting recipient verification.'
        );
    }
}
