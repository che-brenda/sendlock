<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Log;

/**
 * Stub channel: records the verification code to the application log instead of
 * sending it through an external provider. Used until real SMS/WhatsApp/email
 * transports are configured.
 */
class LogVerificationChannel implements VerificationChannel
{
    public function send(string $to, string $code, string $context): void
    {
        Log::info('SendLock verification code issued', [
            'to' => $to,
            'code' => $code,
            'context' => $context,
        ]);
    }
}
