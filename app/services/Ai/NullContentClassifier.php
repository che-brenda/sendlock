<?php

namespace App\Services\Ai;

/**
 * The default AI classifier: contributes nothing. Keeps scans (and the test
 * suite) fully offline and free with no AI provider configured. Switch via
 * SENDLOCK_AI_DRIVER=gemini (beta) or =claude (production).
 */
class NullContentClassifier implements ContentClassifier
{
    public function classify(?string $subject, ?string $content): array
    {
        return ['score' => 0, 'findings' => []];
    }
}
