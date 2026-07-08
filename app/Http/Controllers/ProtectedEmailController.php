<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\ApprovalRequest;
use App\Models\EmailScan;
use App\Models\TrustedDomain;
use App\Models\VerifiedRecipient;
use App\Services\ApprovalWorkflow;
use App\Services\FlaggedDomainService;
use App\Services\PublicEmailProviders;
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
            // Where to land after the request is created (from the flagged-domain popup).
            'intent' => 'nullable|in:show,verify,analysis',
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

        // Persist the scan so its full risk analysis is viewable (the "View risk
        // analysis" option links to it), and link it to the approval request.
        $scan = EmailScan::create([
            'organization_id' => $organizationId,
            'user_id' => auth()->id(),
            'sender_email' => $validated['recipient_email'],
            'sender_domain' => $evaluation['domain'],
            'subject' => $validated['subject'] ?? null,
            'email_content' => $validated['email_content'] ?? null,
            'risk_score' => $evaluation['risk_score'],
            'risk_level' => $evaluation['risk_level'],
            'decision' => $evaluation['decision'],
            'confidence' => $evaluation['confidence'],
            'recommendations' => $evaluation['recommendations'],
            'findings' => $evaluation['findings'],
            'analysis' => $evaluation['analysis'] ?? [],
            'is_trusted_domain' => $evaluation['signals']['is_trusted_domain'] ?? false,
            'is_blocked_domain' => $evaluation['signals']['is_blocked_domain'] ?? false,
            'spf_pass' => (bool) ($evaluation['signals']['spf_pass'] ?? false),
            'dkim_pass' => (bool) ($evaluation['signals']['dkim_pass'] ?? false),
            'dmarc_pass' => (bool) ($evaluation['signals']['dmarc_pass'] ?? false),
        ]);

        $approvalRequest = $workflow->createFromEvaluation(
            $evaluation,
            [
                'recipient_email' => $validated['recipient_email'],
                'subject' => $validated['subject'] ?? null,
                'email_content' => $validated['email_content'] ?? null,
                'email_scan_id' => $scan->id,
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

        $flash = ['success' => 'Request submitted. Status: '.$approvalRequest->status];

        // Honour the option chosen on the flagged-domain popup.
        return match ($request->input('intent')) {
            'verify' => redirect()->route('recipient-verification.index')->with($flash),
            'analysis' => redirect()->route('email-scans.show', $scan)->with($flash),
            default => redirect()->route('protected-email.show', $approvalRequest)->with($flash),
        };
    }

    public function show(ApprovalRequest $approvalRequest, ApprovalWorkflow $workflow)
    {
        $this->authorizeTenant($approvalRequest);

        $approvalRequest->load(['verifications', 'actions.user', 'user']);

        $alreadyTrusted = $workflow->isTrustedCounterparty(
            $approvalRequest->recipient_email,
            $approvalRequest->organization_id
        );

        return view('protected-email.show', compact('approvalRequest', 'alreadyTrusted'));
    }

    /**
     * The final human action: dispatch a message that has been cleared/released.
     * Nothing is sent automatically — even a safe, fully-verified message waits
     * here for the user to review the "safe to send" confirmation and press Send.
     */
    public function send(ApprovalRequest $approvalRequest)
    {
        $this->authorizeTenant($approvalRequest);

        abort_unless(
            $approvalRequest->status === ApprovalRequest::STATUS_RELEASED,
            422,
            'Only a released (cleared) message can be sent.'
        );

        if ($approvalRequest->sent_at === null) {
            $approvalRequest->update(['sent_at' => now()]);

            AuditLogger::log(
                'EMAIL_SENT',
                'APPROVAL_REQUEST',
                $approvalRequest->id,
                'Sent protected email to '.$approvalRequest->recipient_email
            );
        }

        return back()->with('success', 'Email sent to '.$approvalRequest->recipient_email.'.');
    }

    /**
     * The sender abandons an in-flight send (nothing is dispatched).
     */
    public function cancel(ApprovalRequest $approvalRequest)
    {
        $this->authorizeTenant($approvalRequest);

        abort_if($approvalRequest->isTerminal(), 422, 'This request is already finalized.');

        $approvalRequest->update(['status' => ApprovalRequest::STATUS_CANCELLED]);

        AuditLogger::log(
            'CANCELLED',
            'APPROVAL_REQUEST',
            $approvalRequest->id,
            'Cancelled protected send to '.$approvalRequest->recipient_email
        );

        return redirect()->route('protected-email.create')->with('success', 'Send cancelled.');
    }

    /**
     * Escalate an untrusted send to a higher authority (manager approval)
     * instead of recipient verification.
     */
    public function escalate(ApprovalRequest $approvalRequest, ApprovalWorkflow $workflow)
    {
        $this->authorizeTenant($approvalRequest);

        abort_if($approvalRequest->isTerminal(), 422, 'This request is already finalized.');

        $workflow->escalateToApproval($approvalRequest);

        AuditLogger::log(
            'ESCALATED',
            'APPROVAL_REQUEST',
            $approvalRequest->id,
            'Escalated to manager authorization for '.$approvalRequest->recipient_email
        );

        return back()->with('success', 'Escalated — a manager must now authorize this send.');
    }

    /**
     * On explicit user confirmation, add a cleared recipient to the trusted
     * database — either the exact address (verified contact) or its whole domain
     * (trusted domain). Permitted ONLY after the send passed BOTH recipient
     * verification AND manager approval, so trust is never granted off a bare
     * auto-release.
     */
    public function trustRecipient(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->authorizeTenant($approvalRequest);

        abort_unless(
            $approvalRequest->wasVerifiedAndApproved(),
            422,
            'A recipient can only be trusted after it has been verified and approved.'
        );

        $validated = $request->validate([
            'scope' => 'required|in:address,domain',
        ]);

        $email = strtolower(trim($approvalRequest->recipient_email));

        if ($validated['scope'] === 'domain') {
            $domain = RiskEngine::domainFromEmail($email);

            // Never trust a public provider as a whole domain — trust the address.
            if (PublicEmailProviders::is($domain)) {
                return back()->withErrors([
                    'scope' => $domain.' is a public email provider — trust the specific address instead of the whole domain.',
                ]);
            }

            TrustedDomain::firstOrCreate(
                ['organization_id' => $approvalRequest->organization_id, 'domain' => $domain],
                ['active' => true],
            );

            AuditLogger::log('CREATE', 'TRUSTED_DOMAIN', null, 'Trusted domain '.$domain.' after verified & approved send');

            return back()->with('success', 'Domain '.$domain.' added to your Trusted Domains — future senders on it are trusted.');
        }

        VerifiedRecipient::firstOrCreate(
            ['organization_id' => $approvalRequest->organization_id, 'email' => $email],
            ['verified' => true, 'verified_at' => now()],
        );

        AuditLogger::log('CREATE', 'VERIFIED_RECIPIENT', null, 'Trusted recipient '.$email.' after verified & approved send');

        return back()->with('success', 'Recipient added to your Verified Contacts — future sends to this address are trusted.');
    }

    private function authorizeTenant(ApprovalRequest $approvalRequest): void
    {
        abort_unless(
            $approvalRequest->organization_id === auth()->user()->organization_id,
            403
        );
    }
}
