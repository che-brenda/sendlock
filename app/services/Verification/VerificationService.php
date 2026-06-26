<?php

namespace App\Services\Verification;

use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\RecipientVerification;
use Illuminate\Support\Carbon;

/**
 * Issues and checks recipient verification codes. Delivery is delegated to a
 * {@see VerificationChannel} resolved per channel type: SMS/WhatsApp use the
 * configured driver (e.g. Twilio), while email and the default 'log' driver use
 * the log stub.
 */
class VerificationService
{
    /**
     * Issue a new code for the request over the given channel and deliver it.
     */
    public function issue(ApprovalRequest $request, string $channel, ?string $phone = null): RecipientVerification
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $ttl = (int) config('sendlock.verification.code_ttl', 15);

        // Supersede any earlier pending challenges for this request.
        RecipientVerification::where('approval_request_id', $request->id)
            ->where('status', RecipientVerification::STATUS_PENDING)
            ->update(['status' => RecipientVerification::STATUS_EXPIRED]);

        $verification = RecipientVerification::create([
            'approval_request_id' => $request->id,
            'organization_id' => $request->organization_id,
            'recipient_email' => $request->recipient_email,
            'recipient_phone' => $phone,
            'channel' => $channel,
            'code' => $code,
            'status' => RecipientVerification::STATUS_PENDING,
            'expires_at' => Carbon::now()->addMinutes($ttl),
        ]);

        $to = $channel === 'email' ? $request->recipient_email : ($phone ?? $request->recipient_email);

        $this->resolveChannel($channel, $request->organization_id)->send(
            $to,
            $code,
            'Verify recipient for "'.($request->subject ?: 'outbound email').'"'
        );

        return $verification;
    }

    /**
     * Check a submitted code. Returns true on success and marks the
     * verification VERIFIED.
     */
    public function check(ApprovalRequest $request, string $code): bool
    {
        $verification = RecipientVerification::where('approval_request_id', $request->id)
            ->where('status', RecipientVerification::STATUS_PENDING)
            ->latest()
            ->first();

        if ($verification === null) {
            return false;
        }

        if ($verification->isExpired()) {
            $verification->update(['status' => RecipientVerification::STATUS_EXPIRED]);

            return false;
        }

        if (! hash_equals($verification->code, trim($code))) {
            return false;
        }

        $verification->update([
            'status' => RecipientVerification::STATUS_VERIFIED,
            'verified_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Pick the transport for a given channel type. SMS/WhatsApp go through the
     * configured driver (Twilio) **only when the tenant's plan entitles the
     * paid channel** — an unentitled org falls back to the log stub, so a free/
     * beta org never triggers a billable send even if Twilio is configured.
     * Email and the default 'log' driver always use the log stub. Unconfigured
     * Twilio also degrades to the stub inside the channel.
     */
    private function resolveChannel(string $channel, int $organizationId): VerificationChannel
    {
        $driver = config('sendlock.verification.driver', 'log');

        if ($driver === 'twilio'
            && in_array($channel, ['sms', 'whatsapp'], true)
            && $this->entitled($organizationId, $channel)) {
            return new TwilioVerificationChannel($channel);
        }

        return new LogVerificationChannel;
    }

    /**
     * Whether the org's plan includes the paid verification channel.
     */
    private function entitled(int $organizationId, string $channel): bool
    {
        $feature = $channel === 'whatsapp' ? 'whatsapp_verification' : 'sms_verification';

        return (bool) Organization::find($organizationId)?->hasFeature($feature);
    }
}
