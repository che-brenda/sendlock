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

        // Bulk phishing-list feeds imported on a schedule into threat_intel_cache
        // (distinct from the per-domain live feeds above). "enabled" is a
        // comma-separated list of list-feed keys (openphish, phishtank); empty =
        // the importer is a no-op, so nothing is fetched without opting in.
        'lists' => [
            'enabled' => array_values(array_filter(array_map('trim', explode(',', (string) env('SENDLOCK_THREAT_LISTS', ''))))),
            'openphish_url' => env('OPENPHISH_FEED_URL', 'https://openphish.com/feed.txt'),
            'phishtank_url' => env('PHISHTANK_FEED_URL', 'https://data.phishtank.com/data/online-valid.json'),
            'phishtank_key' => env('PHISHTANK_API_KEY'),
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

        'claude' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans & feature entitlements
    |--------------------------------------------------------------------------
    |
    | Maps an organization's `subscription_plan` (case-insensitive) to the
    | features it may use. This is the gate that keeps PAID providers from firing
    | for non-entitled tenants even when global credentials are set — a free/beta
    | org never triggers a billable call. "*" grants everything. Unknown plans
    | fall back to `default_plan`.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | DNS / MX lookups
    |--------------------------------------------------------------------------
    |
    | Driver for the MX-record signal. "live" uses PHP's built-in resolver
    | (checkdnsrr — no API key), "null" checks nothing (unknown). Defaults to
    | live in dev/prod; the test suite pins it to null so it never hits the
    | network (see phpunit.xml).
    |
    */

    'dns' => [
        'driver' => env('SENDLOCK_DNS_DRIVER', 'live'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain age (registration date)
    |--------------------------------------------------------------------------
    |
    | Driver that resolves how long ago a domain was registered. "null" (default)
    | returns unknown — the signal is present but inert until a provider is
    | enabled. "rdap" queries rdap.org (free, keyless) over HTTP.
    |
    */

    'domain_age' => [
        'driver' => env('SENDLOCK_DOMAIN_AGE_DRIVER', 'null'),
        'rdap_url' => env('SENDLOCK_RDAP_URL', 'https://rdap.org/domain/'),
        'timeout' => env('SENDLOCK_DOMAIN_AGE_TIMEOUT', 8),
    ],

    'default_plan' => env('SENDLOCK_DEFAULT_PLAN', 'free'),

    'plans' => [
        'free' => [],
        'beta' => ['ai_classification'],
        'pro' => ['ai_classification', 'sms_verification', 'whatsapp_verification'],
        'enterprise' => ['*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing — purchasable packages & payment methods
    |--------------------------------------------------------------------------
    |
    | The three subscription packages presented on the billing page after sign
    | up. Each package's `plan` maps to a key in `plans` above, so paying for a
    | package is what grants its feature entitlements. Prices are illustrative
    | (the payment step is a stub — no real charge is made). `payment_methods`
    | drives the checkout method picker.
    |
    */

    'billing' => [

        'currency' => env('SENDLOCK_BILLING_CURRENCY', 'USD'),
        'currency_symbol' => env('SENDLOCK_BILLING_CURRENCY_SYMBOL', '$'),

        // Ordered for display (left → right). The `plan` key links to `plans`.
        // A package priced at 0 is activated without payment (see BillingController).
        'packages' => [

            'free' => [
                'plan' => 'free',
                'name' => 'Free',
                'price' => 0,
                'period' => 'mo',
                'tagline' => 'Core outbound protection to get started.',
                'highlighted' => false,
                'features' => [
                    'Up to 5 users',
                    'Domain & content risk scoring',
                    'Trust Center (domains & vendors)',
                    'Email recipient verification',
                    'Audit logs',
                    'Community support',
                ],
            ],

            'starter' => [
                'plan' => 'beta',
                'name' => 'Starter',
                'price' => 29,
                'period' => 'mo',
                'tagline' => 'Essential outbound protection for small teams.',
                'highlighted' => false,
                'features' => [
                    'Up to 25 users',
                    'Domain & content risk scoring',
                    'AI content classification',
                    'Trust Center (domains & vendors)',
                    'Email & in-app alerts',
                    'Standard support',
                ],
            ],

            'professional' => [
                'plan' => 'pro',
                'name' => 'Professional',
                'price' => 99,
                'period' => 'mo',
                'tagline' => 'Full BEC defense with recipient verification.',
                'highlighted' => true,
                'features' => [
                    'Up to 250 users',
                    'Everything in Starter',
                    'SMS & WhatsApp recipient verification',
                    'Approval workflows',
                    'Financial / bank-change detection',
                    'Priority support',
                ],
            ],

            'enterprise' => [
                'plan' => 'enterprise',
                'name' => 'Enterprise',
                'price' => 299,
                'period' => 'mo',
                'tagline' => 'Unlimited scale with every signal enabled.',
                'highlighted' => false,
                'features' => [
                    'Unlimited users & sub-organizations',
                    'Everything in Professional',
                    'Threat-intelligence feeds & OCR',
                    'Dedicated success manager',
                    'SSO & audit exports',
                    '24/7 enterprise support',
                ],
            ],

        ],

        'payment_methods' => [
            'visa' => ['name' => 'Card (Visa / Mastercard)', 'kind' => 'card'],
            'mtn_momo' => ['name' => 'MTN Mobile Money', 'kind' => 'mobile_money'],
            'paypal' => ['name' => 'PayPal', 'kind' => 'wallet'],
        ],
    ],

];
