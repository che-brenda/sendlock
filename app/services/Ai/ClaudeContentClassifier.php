<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

/**
 * AI content classification via the Anthropic Claude Messages API (production /
 * paid tier). Raw HTTP (no SDK), strict-JSON output via `output_config.format`,
 * so the verdict is always schema-valid. Same contract as
 * {@see GeminiContentClassifier} — promoting beta → production is a driver swap
 * (SENDLOCK_AI_DRIVER=claude). Degrades to an empty result on any error, missing
 * key, empty content, refusal, or malformed response — never throws.
 *
 * Model defaults to claude-opus-4-8 (override with ANTHROPIC_MODEL); request
 * shape per the Anthropic Messages API (anthropic-version 2023-06-01).
 */
class ClaudeContentClassifier implements ContentClassifier
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    /** AI alone cannot exceed a HIGH classification without other signals. */
    private const MAX_SCORE = 50;

    /** Strict JSON schema the model must return. */
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'risk' => ['type' => 'integer'],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['risk', 'categories', 'reasons'],
        'additionalProperties' => false,
    ];

    public function classify(?string $subject, ?string $content): array
    {
        $empty = ['score' => 0, 'findings' => []];

        $text = trim(($subject ?? '').' '.($content ?? ''));
        $key = config('sendlock.ai.claude.key');

        if ($text === '' || empty($key)) {
            return $empty;
        }

        try {
            $response = Http::timeout((int) config('sendlock.ai.timeout', 12))
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => self::VERSION,
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('sendlock.ai.claude.model', 'claude-opus-4-8'),
                    'max_tokens' => 1024,
                    'system' => 'You are an email security analyst detecting Business Email Compromise (BEC), '
                        .'phishing, invoice/payment fraud, and social-engineering. Assess the email and return '
                        .'a risk integer 0-100, short category strings, and short reason strings.',
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Assess this email:\n---\n".$text."\n---",
                    ]],
                    'output_config' => [
                        'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA],
                    ],
                ]);
        } catch (\Throwable $e) {
            return $empty;
        }

        if (! $response->successful()) {
            return $empty;
        }

        // A safety refusal returns 200 with stop_reason "refusal" and no usable
        // content — treat as no signal.
        if ($response->json('stop_reason') === 'refusal') {
            return $empty;
        }

        return self::parse($response->json('content.0.text'));
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
