<?php

namespace App\Services\Ai;

use App\Services\ContentIntelligenceService;
use App\Services\RiskEngine;

/**
 * AI classification of email content — the deep pass that runs after the cheap
 * rule-based {@see ContentIntelligenceService}. Provider-agnostic:
 * Gemini (free tier, beta) and Claude (paid, production) implement this same
 * contract, so promoting beta → production is a driver swap, not a code change.
 *
 * Implementations return a partial signal set `['score' => int, 'findings' =>
 * string[]]` that {@see RiskEngine} composes additively, and must
 * degrade to an empty result on any provider error — AI is an enhancement, never
 * a hard dependency.
 */
interface ContentClassifier
{
    /**
     * @return array{score:int, findings:string[]}
     */
    public function classify(?string $subject, ?string $content): array;
}
