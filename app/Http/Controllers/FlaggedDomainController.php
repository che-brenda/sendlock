<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\FlaggedDomain;
use App\Services\ApprovalWorkflow;
use App\Services\RiskEngine;
use Illuminate\Http\Request;

/**
 * Review register for domains the risk engine auto-flagged (lookalike, typosquat,
 * or untrusted), plus the "request manager authorization" escalation a user can
 * trigger from the flagged-domain popup warning.
 */
class FlaggedDomainController extends Controller
{
    public function index()
    {
        $flaggedDomains = FlaggedDomain::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('last_seen_at')
            ->get();

        return view('flagged-domains.index', compact('flaggedDomains'));
    }

    /**
     * Escalate a flagged-domain send for manager authorization. Reuses the
     * standard approval workflow but forces at least manager sign-off, since the
     * user is overriding a warning rather than sending automatically.
     */
    public function requestApproval(Request $request, ApprovalWorkflow $workflow)
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email',
            'subject' => 'nullable|string|max:255',
            'email_content' => 'nullable|string',
        ]);

        $organizationId = auth()->user()->organization_id;

        $evaluation = RiskEngine::evaluate([
            'sender_email' => $validated['recipient_email'],
            'subject' => $validated['subject'] ?? null,
            'email_content' => $validated['email_content'] ?? null,
        ], $organizationId);

        // The user is explicitly escalating, so never let it release automatically.
        if ($evaluation['decision'] === 'ALLOW') {
            $evaluation['decision'] = 'MANAGER_APPROVAL';
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
            'FLAGGED_DOMAIN_ESCALATION',
            'APPROVAL_REQUEST',
            $approvalRequest->id,
            'Manager authorization requested for flagged domain '.$evaluation['domain']
        );

        return redirect()
            ->route('protected-email.show', $approvalRequest)
            ->with('success', 'Authorization requested. Status: '.$approvalRequest->status);
    }
}
