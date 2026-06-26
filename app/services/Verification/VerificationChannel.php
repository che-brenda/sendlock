<?php

namespace App\Services\Verification;

/**
 * A transport that delivers a verification code to a recipient. Implementations
 * back a channel (SMS / WhatsApp / email). The default {@see LogVerificationChannel}
 * simply logs; real providers implement the same contract.
 */
interface VerificationChannel
{
    public function send(string $to, string $code, string $context): void;
}
