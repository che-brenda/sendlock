<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Firewall (WAF)
    |--------------------------------------------------------------------------
    |
    | A request-level firewall applied to the whole `web` group. It inspects the
    | request LINE (path + query string), the headers and the client, blocks known
    | attack signatures, hardens every response with security headers, and records
    | blocked attempts. It deliberately does NOT scan the request body — this app's
    | whole purpose is analysing suspicious email content, so bodies legitimately
    | contain "malicious-looking" text and must never be firewalled.
    |
    */

    'enabled' => env('FIREWALL_ENABLED', true),

    // Reject absurdly long request lines outright (buffer-overflow / fuzzing).
    'max_uri_length' => (int) env('FIREWALL_MAX_URI', 2048),

    // Signatures matched against the decoded path + query string only.
    'malicious_patterns' => [
        'path_traversal' => '#(\.\./|\.\.\\\\|%2e%2e)#i',
        'null_byte' => '#(\x00|%00)#i',
        'xss' => '#(<script\b|javascript:|\bon\w+\s*=\s*["\'])#i',
        'sql_injection' => '#(\bunion\b\s+\bselect\b|\binformation_schema\b|\bsleep\s*\(|\bbenchmark\s*\(|--\s|;--)#i',
        'lfi_rfi' => '#(/etc/passwd|/proc/self|php://|data://|expect://)#i',
        'code_exec' => '#(base64_decode|shell_exec|system\s*\(|passthru\s*\(|\beval\s*\()#i',
    ],

    // Automated attack tooling — matched (case-insensitive) against User-Agent.
    'blocked_agents' => [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'nessus', 'acunetix',
        'fimap', 'dirbuster', 'gobuster', 'w3af', 'havij', 'zgrab',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response security headers
    |--------------------------------------------------------------------------
    |
    | Added to every response. The CSP allows 'unsafe-inline'/'unsafe-eval'
    | because the UI uses Alpine.js and scoped inline scripts (e.g. the risk
    | chart); it still restricts sources to self + the font host, and locks down
    | framing, base-uri and form-action.
    |
    */

    'headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-XSS-Protection' => '0',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
        'Content-Security-Policy' => env('FIREWALL_CSP',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
            "font-src 'self' https://fonts.bunny.net data:; ".
            "img-src 'self' data:; ".
            "base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
        ),
    ],

    // HSTS is only emitted over HTTPS (max-age in seconds; 1 year).
    'hsts' => env('FIREWALL_HSTS', 'max-age=31536000; includeSubDomains'),
];
