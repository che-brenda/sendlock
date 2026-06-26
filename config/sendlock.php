<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recipient verification
    |--------------------------------------------------------------------------
    |
    | The verification channel driver. "log" writes the code to the application
    | log (no external account required). Real providers (e.g. Twilio for SMS /
    | WhatsApp, a mail transport for email) are wired in later by adding a driver
    | and switching this value via SENDLOCK_VERIFICATION_DRIVER.
    |
    */

    'verification' => [
        'driver' => env('SENDLOCK_VERIFICATION_DRIVER', 'log'),

        // Minutes a verification code stays valid.
        'code_ttl' => env('SENDLOCK_VERIFICATION_TTL', 15),

        // Twilio credentials for the 'twilio' driver (SMS + WhatsApp). Leaving
        // any of these blank causes the channel to fall back to the log stub,
        // so nothing is ever sent (or billed) until they are configured.
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'sms_from' => env('TWILIO_SMS_FROM'),
            'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email authentication (SPF / DKIM / DMARC)
    |--------------------------------------------------------------------------
    |
    | Driver that resolves a sender domain's authentication posture. "null"
    | returns unknown for every check (no external lookups, no score impact) —
    | the safe default. A real DNS/header driver is wired in later. Scans may
    | also pass explicit per-message results which always take precedence.
    |
    */

    'email_auth' => [
        'driver' => env('SENDLOCK_EMAIL_AUTH_DRIVER', 'null'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment OCR
    |--------------------------------------------------------------------------
    |
    | Driver that extracts text from uploaded images / scanned documents so the
    | content engines can analyse it. "null" extracts nothing (the safe default,
    | no binary required). "tesseract" shells out to the self-hosted Tesseract
    | binary (must be installed and on PATH) — switch via SENDLOCK_OCR_DRIVER.
    |
    */

    'ocr' => [
        'driver' => env('SENDLOCK_OCR_DRIVER', 'null'),

        // Path/name of the tesseract binary and per-file timeout (seconds).
        'tesseract_binary' => env('TESSERACT_BINARY', 'tesseract'),
        'timeout' => env('SENDLOCK_OCR_TIMEOUT', 30),
    ],

];
