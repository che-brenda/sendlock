<?php

namespace App\Services;

/**
 * Content-layer signals: scans the subject and body for fraud-intent language
 * (payment, banking-change, urgency, logistics and insurance cues). Each match
 * raises the risk score; the contribution is capped so content alone cannot
 * exceed a HIGH classification without other signals.
 */
class ContentIntelligenceService
{
    /**
     * Intent phrase => weight. Phrases are matched case-insensitively as
     * substrings of "subject + body".
     */
    private const INTENT_PHRASES = [
        'change of bank account' => 35,
        'change of account details' => 35,
        'new bank account' => 35,
        'new beneficiary' => 35,
        'change of beneficiary' => 35,
        'update our bank' => 35,
        'wire transfer' => 25,
        'urgent payment' => 25,
        'payment instruction' => 20,
        'confidential' => 10,
        'bank details' => 20,
        'invoice' => 10,
        'purchase order' => 10,
        'bill of lading' => 15,
        'change of consignee' => 20,
        'release cargo' => 20,
        'claims payout' => 20,
    ];

    private const MAX_CONTENT_SCORE = 65;

    public static function analyze(?string $subject, ?string $content): array
    {
        $haystack = strtolower(trim(($subject ?? '') . ' ' . ($content ?? '')));

        if ($haystack === '') {
            return ['score' => 0, 'findings' => []];
        }

        $score = 0;
        $findings = [];

        foreach (self::INTENT_PHRASES as $phrase => $weight) {
            if (str_contains($haystack, $phrase)) {
                $score += $weight;
                $findings[] = 'Fraud-intent language detected: "' . $phrase . '"';
            }
        }

        return [
            'score' => min($score, self::MAX_CONTENT_SCORE),
            'findings' => $findings,
        ];
    }
}
