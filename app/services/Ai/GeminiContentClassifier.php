<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

/**
 * AI content classification via Google's Gemini API (free tier — beta provider).
 * Asks the model for a strict-JSON BEC/fraud assessment and maps it to a capped
 * additive score. Degrades to an empty result on any error, missing key, empty
 * content, or malformed response — never throws, never blocks a scan.
 */
class GeminiContentClassifier implements ContentClassifier
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** AI alone cannot exceed a HIGH classification without other signals. */
    private const MAX_SCORE = 50;

    public function classify(?string $subject, ?string $content): array
    {
        $empty = ['score' => 0, 'findings' => []];

        $text = trim(($subject ?? '').' '.($content ?? ''));
        $key = config('sendlock.ai.gemini.key');

        if ($text === '' || empty($key)) {
            return $empty;
        }

        $model = (string) config('sendlock.ai.gemini.model', 'gemini-1.5-flash');

        try {
            $response = Http::timeout((int) config('sendlock.ai.timeout', 12))
                ->post(self::ENDPOINT.$model.':generateContent?key='.$key, [
                    'contents' => [[
                        'parts' => [['text' => self::prompt($text)]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'response_mime_type' => 'application/json',
                    ],
                ]);
        } catch (\Throwable $e) {
            return $empty;
        }

        if (! $response->successful()) {
            return $empty;
        }

        return self::parse($response->json('candidates.0.content.parts.0.text'));
    }

    private static function prompt(string $text): string
    {
        return <<<PROMPT
            You are an email security analyst detecting Business Email Compromise (BEC),
            phishing, invoice/payment fraud, and social-engineering. Assess the email
            below and respond ONLY with JSON of the form:
            {"risk": <integer 0-100>, "categories": [<short strings>], "reasons": [<short strings>]}

            Email:
            ---
            $text
            ---
            PROMPT;
    }

    /**
     * @return array{score:int, findings:string[]}
     */
    private static function parse(mixed $json): array
    {
        $empty = ['score' => 0, 'findings' => []];

        if (! is_string($json) || trim($json) === '') {
            return $empty;
        }

        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['risk'])) {
            return $empty;
        }

        $risk = (int) $data['risk'];
        $score = max(0, min(self::MAX_SCORE, (int) round($risk * self::MAX_SCORE / 100)));

        if ($score === 0) {
            return $empty;
        }

        $reasons = array_values(array_filter(array_map(
            fn ($r) => is_string($r) ? trim($r) : '',
            (array) ($data['reasons'] ?? [])
        )));

        $findings = $reasons === []
            ? ['AI assessed elevated fraud risk in the message content']
            : array_map(fn ($r) => 'AI: '.$r, array_slice($reasons, 0, 3));

        return ['score' => $score, 'findings' => $findings];
    }
}
