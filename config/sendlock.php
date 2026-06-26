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

    /*
    |--------------------------------------------------------------------------
    | External threat-intelligence feeds
    |--------------------------------------------------------------------------
    |
    | Reputation sources consulted (in order) when a domain is not on the curated
    | platform list. "enabled" is a comma-separated list of feed keys
    | (e.g. "google_safe_browsing,virustotal"); empty = no external calls, the
    | safe default. A feed only runs if its API key is also set. Verdicts are
    | cached in `threat_intel_cache` for `cache_ttl` minutes to respect free-tier
    | rate limits.
    |
    */

    'threat_feeds' => [
        'enabled' => array_values(array_filter(array_map('trim', explode(',', (string) env('SENDLOCK_THREAT_FEEDS', ''))))),

        'cache_ttl' => env('SENDLOCK_THREAT_CACHE_TTL', 720),

        'google_safe_browsing' => [
            'key' => env('GOOGLE_SAFE_BROWSING_KEY'),
        ],

        'virustotal' => [
            'key' => env('VIRUSTOTAL_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI content classification
    |--------------------------------------------------------------------------
    |
    | Deep content analysis behind the cheap rule-based pass. "null" contributes
    | nothing (default). "gemini" uses Google's free-tier Gemini API (beta);
    | "claude" uses the Anthropic API (production) — both implement the same
    | ContentClassifier contract, so it's a driver swap. Degrades to no signal on
    | any provider error.
    |
    */

    'ai' => [
        'driver' => env('SENDLOCK_AI_DRIVER', 'null'),
        'timeout' => env('SENDLOCK_AI_TIMEOUT', 12),

        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        ],
    ],

];
