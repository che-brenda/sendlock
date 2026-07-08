<?php

namespace App\Services;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\TrustedDomain;
use App\Models\VerifiedRecipient;
use Illuminate\Support\Carbon;

/**
 * Drives an outbound email through the verification + approval workflow based on
 * the risk engine's decision:
 *
 *   ALLOW             -> released immediately
 *   MANAGER_APPROVAL  -> manager approval required
 *   RECIPIENT_VERIFY  -> recipient verification, then manager approval
 *   QUARANTINE        -> blocked
 *
 * A recipient already on the org's Verified Recipients list skips the
 * verification step.
 */
class ApprovalWorkflow
{
    /**
     * @param  array{risk_score:int, risk_level:string, decision:string, confidence?:int, recommendations?:string[]}  $evaluation
     */
    public function createFromEvaluation(array $evaluation, array $email, int $organizationId, int $userId): ApprovalRequest
    {
        $decision = $evaluation['decision'];

        $requiresVerification = $decision === 'RECIPIENT_VERIFY';
        $requiresApproval = in_array($decision, ['MANAGER_APPROVAL', 'RECIPIENT_VERIFY'], true);

        // A pre-verified recipient does not need re-verification.
        if ($requiresVerification && $this->isVerifiedRecipient($email['recipient_email'], $organizationId)) {
            $requiresVerification = false;
        }

        // SECURITY GATE — an unregistered (untrusted) counterparty can NEVER be
        // released automatically. Only a domain/address the org has registered as
        // trusted may auto-send; everything else must clear a human step — at
        // minimum manager approval (and, for higher-risk decisions, recipient
        // verification first). This is what stops a fresh look-alike or any
        // unknown address slipping out on an ALLOW verdict.
        if ($decision !== 'QUARANTINE'
            && ! $this->isTrustedCounterparty($email['recipient_email'], $organizationId)) {
            $requiresApproval = true;
        }

        $request = new ApprovalRequest([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'email_scan_id' => $email['email_scan_id'] ?? null,
            'recipient_email' => $email['recipient_email'],
            'subject' => $email['subject'] ?? null,
            'email_content' => $email['email_content'] ?? null,
            'risk_score' => $evaluation['risk_score'],
            'risk_level' => $evaluation['risk_level'],
            'decision' => $decision,
            'confidence' => $evaluation['confidence'] ?? null,
            'recommendations' => $evaluation['recommendations'] ?? null,
            'requires_verification' => $requiresVerification,
            'requires_approval' => $requiresApproval,
        ]);

        if ($decision === 'QUARANTINE') {
            $request->status = ApprovalRequest::STATUS_BLOCKED;
        } else {
            $request->status = $this->nextStatus($request);
        }

        if ($request->status === ApprovalRequest::STATUS_RELEASED) {
            $request->released_at = Carbon::now();
        }

        $request->save();

        return $request;
    }

    /**
     * Record that the recipient has been verified and advance the request.
     */
    public function markRecipientVerified(ApprovalRequest $request): ApprovalRequest
    {
        $request->recipient_verified_at = Carbon::now();
        $request->requires_verification = false;
        $request->status = $this->nextStatus($request);

        if ($request->status === ApprovalRequest::STATUS_RELEASED) {
            $request->released_at = Carbon::now();
        }

        $request->save();

        return $request;
    }

    public function approve(ApprovalRequest $request, int $userId, ?string $notes = null): ApprovalRequest
    {
        ApprovalAction::create([
            'approval_request_id' => $request->id,
            'organization_id' => $request->organization_id,
            'user_id' => $userId,
            'action' => ApprovalAction::ACTION_APPROVED,
            'notes' => $notes,
        ]);

        $request->requires_approval = false;
        $request->status = $this->nextStatus($request);

        if ($request->status === ApprovalRequest::STATUS_RELEASED) {
            $request->released_at = Carbon::now();
        }

        $request->save();

        return $request;
    }

    public function reject(ApprovalRequest $request, int $userId, ?string $notes = null): ApprovalRequest
    {
        ApprovalAction::create([
            'approval_request_id' => $request->id,
            'organization_id' => $request->organization_id,
            'user_id' => $userId,
            'action' => ApprovalAction::ACTION_REJECTED,
            'notes' => $notes,
        ]);

        $request->status = ApprovalRequest::STATUS_REJECTED;
        $request->save();

        return $request;
    }

    /**
     * Escalate to a higher authority: the sender chooses the manager-approval
     * route instead of recipient verification. Clears the verification
     * requirement but a manager must still approve before release, so an
     * untrusted send is never released without a human decision.
     */
    public function escalateToApproval(ApprovalRequest $request): ApprovalRequest
    {
        $request->requires_verification = false;
        $request->requires_approval = true;
        $request->status = $this->nextStatus($request);
        $request->save();

        return $request;
    }

    /**
     * Compute the next status from the outstanding requirements.
     */
    private function nextStatus(ApprovalRequest $request): string
    {
        if ($request->requires_verification) {
            return ApprovalRequest::STATUS_PENDING_VERIFICATION;
        }

        if ($request->requires_approval) {
            return ApprovalRequest::STATUS_PENDING_APPROVAL;
        }

        return ApprovalRequest::STATUS_RELEASED;
    }

    private function isVerifiedRecipient(string $email, int $organizationId): bool
    {
        return VerifiedRecipient::where('organization_id', $organizationId)
            ->where('email', strtolower(trim($email)))
            ->where('verified', true)
            ->exists();
    }

    /**
     * A counterparty the org has explicitly registered as trusted — either a
     * verified recipient (exact address) or a trusted domain. This is the single
     * gate that decides whether a send may auto-release.
     */
    public function isTrustedCounterparty(string $email, int $organizationId): bool
    {
        if ($this->isVerifiedRecipient($email, $organizationId)) {
            return true;
        }

        $domain = RiskEngine::domainFromEmail($email);

        // A public provider is never blanket-trusted — only the exact verified
        // address (checked above) counts, so an unverified gmail/outlook/… address
        // is always gated.
        if (PublicEmailProviders::is($domain)) {
            return false;
        }

        return TrustedDomain::where('organization_id', $organizationId)
            ->where('domain', $domain)
            ->where('active', true)
            ->exists();
    }
}
