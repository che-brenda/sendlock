<?php

namespace App\Services;

/**
 * Public / free email providers (gmail, outlook, yahoo…). These domains must
 * NEVER be trusted as a whole — millions of unrelated people share them, so
 * "trust gmail.com" would trust every attacker with a gmail address. Only a
 * SPECIFIC verified address at such a domain may be trusted (a VerifiedRecipient),
 * never the domain itself. The trust checks consult this list so a public-provider
 * entry in `trusted_domains` (however it got there) is ignored.
 */
class PublicEmailProviders
{
    public const DOMAINS = [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'msn.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'ymail.com', 'rocketmail.com',
        'icloud.com', 'me.com', 'mac.com',
        'aol.com', 'gmx.com', 'gmx.net', 'zoho.com',
        'proton.me', 'protonmail.com', 'pm.me',
        'yandex.com', 'yandex.ru', 'mail.com', 'mail.ru',
        'tutanota.com', 'fastmail.com', 'hey.com', 'hushmail.com',
    ];

    public static function is(string $domain): bool
    {
        return in_array(strtolower(trim($domain)), self::DOMAINS, true);
    }
}
