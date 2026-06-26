<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflow;
use App\Services\FlaggedDomainService;
use App\Services\RiskEngine;
use Illuminate\Http\Request;

/**
 * The "Send Protected" entry point: an outbound email is risk-scored and routed
 * into the verification / approval workflow before it can be released.
 */
class ProtectedEmailController extends Controller
{
    public function create()
    {
        $requests = ApprovalRequest::where('organization_id', auth()->user()->organization_id)
            ->where('user_id', auth()->id())
            ->latest()
            ->take(15)
            ->get();

        return view('protected-email.create', compact('requests'));
    }

    public function store(Request $request, ApprovalWorkflow $workflow)
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email',
            'subject' => 'nullable|string|max:255',
            'email_content' => 'nullable|string',
            'acknowledged' => 'nullable|boolean',
        ]);

        $organizationId = auth()->user()->organization_id;

        // For outbound protection the counterparty being scored is the recipient.
        $evaluation = RiskEngine::evaluate([
            'sender_email' => $validated['recipient_email'],
            'subject' => $validated['subject'] ?? null,
            'email_content' => $validated['email_content'] ?? null,
        ], $organizationId);

        // Auto-record impersonation / untrusted domains. A repeat use is held
        // behind a popup warning the user must acknowledge (override) before the
        // send proceeds — they may instead escalate for manager authorization.
        if (! $request->boolean('acknowledged')) {
            $record = FlaggedDomainService::record(
                $evaluation['domain'],
                $evaluation['signals']['domain_flags'] ?? [],
                $organizationId,
                auth()->id()
            );

            if ($record && $record['repeat']) {
                $flagged = $record['flagged'];

                return back()->withInput()->with('domain_warning', [
                    'domain' => $flagged->domain,
                    'type' => $flagged->detection_type,
                    'reason' => $flagged->reason,
                    'times_seen' => $flagged->times_seen,
                    'resembles' => $flagged->resembles,
                    'context' => 'send',
                    'email' => [
                        'recipient_email' => $validated['recipient_email'],
                        'subject' => $validated['subject'] ?? null,
                        'email_content' => $validated['email_content'] ?? null,
                    ],
                ]);
            }
        }

        $approvalRequest = $workflow->createFromEvaluation(
            $evaluation,
            [
                'recipient_email' => $validated['recipient_email'],
                'subject' => $validated['subject'] ?? null,
                'email_content' => $validated['email_content'] ?? null,
            ],
            $organizationId,
            auth()->id()
        );

        AuditLogger::log(
            'PROTECTED_SEND',
            'APPROVAL_REQUEST',
            $approvalRequest->id,
            'Send to '.$approvalRequest->recipient_email.' — '.$approvalRequest->status
        );

        return redirect()
            ->route('protected-email.show', $approvalRequest)
            ->with('success', 'Request submitted. Status: '.$approvalRequest->status);
    }

    public function show(ApprovalRequest $approvalRequest)
    {
        $this->authorizeTenant($approvalRequest);

        $approvalRequest->load(['verifications', 'actions.user', 'user']);

        return view('protected-email.show', compact('approvalRequest'));
    }

    private function authorizeTenant(ApprovalRequest $approvalRequest): void
    {
        abort_unless(
            $approvalRequest->organization_id === auth()->user()->organization_id,
            403
        );
    }
}
