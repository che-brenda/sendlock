<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers verification codes over Twilio — SMS or WhatsApp depending on the
 * mode. Uses Twilio's REST Messages API directly via the HTTP client (no SDK
 * dependency). If credentials are not configured it transparently falls back to
 * the log stub, so the workflow never breaks and nothing is billed until the
 * account is set up.
 */
class TwilioVerificationChannel implements VerificationChannel
{
    public function __construct(private string $mode = 'sms') {}

    public function send(string $to, string $code, string $context): void
    {
        $sid = config('sendlock.verification.twilio.sid');
        $token = config('sendlock.verification.twilio.token');
        $from = $this->mode === 'whatsapp'
            ? config('sendlock.verification.twilio.whatsapp_from')
            : config('sendlock.verification.twilio.sms_from');

        // Not configured — degrade gracefully to the log stub.
        if (! $sid || ! $token || ! $from) {
            Log::warning('SendLock Twilio not configured; falling back to log channel.', ['mode' => $this->mode]);
            (new LogVerificationChannel())->send($to, $code, $context);

            return;
        }

        $toAddress = $this->mode === 'whatsapp' ? 'whatsapp:' . $to : $to;
        $fromAddress = $this->mode === 'whatsapp' ? 'whatsapp:' . $from : $from;

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $toAddress,
                'From' => $fromAddress,
                'Body' => "Your SendLock verification code is {$code}. " . $context,
            ]);

        if ($response->failed()) {
            Log::error('SendLock Twilio verification send failed', [
                'mode' => $this->mode,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to send verification code via Twilio.');
        }
    }
}
