<?php

use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\Ai\ClaudeContentClassifier;
use App\Services\Ai\ContentClassifier;
use App\Services\RiskEngine;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['sendlock.ai.driver' => 'claude']);
    config(['sendlock.ai.claude.key' => 'sk-test']);

    // 'enterprise' plan entitles the org to ai_classification.
    $this->org = Organization::create([
        'organization_name' => 'Acme',
        'type' => 'head',
        'status' => true,
        'subscription_plan' => 'enterprise',
    ]);
});

function claudeJson(array $payload): array
{
    return [
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => json_encode($payload)]],
    ];
}

test('the claude driver is resolved when configured', function () {
    expect(app(ContentClassifier::class))->toBeInstanceOf(ClaudeContentClassifier::class);
});

test('claude classifies fraud content into a capped score and findings', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(claudeJson([
            'risk' => 88,
            'categories' => ['bec'],
            'reasons' => ['Urgent bank-account change request'],
        ]), 200),
    ]);

    $result = (new ClaudeContentClassifier)->classify('Urgent', 'Please change our bank account.');

    // risk 88 scaled to the 50 cap -> 44.
    expect($result['score'])->toBe(44);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'AI:')))->toBeTrue();
});

test('claude sends the anthropic version header and strict-JSON schema', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(claudeJson(['risk' => 0, 'categories' => [], 'reasons' => []]), 200)]);

    (new ClaudeContentClassifier)->classify('x', 'hello');

    Http::assertSent(function ($request) {
        return $request->hasHeader('anthropic-version', '2023-06-01')
            && $request->hasHeader('x-api-key', 'sk-test')
            && data_get($request->data(), 'output_config.format.type') === 'json_schema';
    });
});

test('a claude safety refusal degrades to no signal', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['stop_reason' => 'refusal', 'content' => []], 200),
    ]);

    expect((new ClaudeContentClassifier)->classify('x', 'some content')['score'])->toBe(0);
});

test('a claude API failure degrades to no signal', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('error', 500)]);

    expect((new ClaudeContentClassifier)->classify('x', 'some content')['score'])->toBe(0);
});

test('claude with no key makes no call', function () {
    config(['sendlock.ai.claude.key' => null]);
    Http::fake();

    expect((new ClaudeContentClassifier)->classify('x', 'content')['score'])->toBe(0);
    Http::assertNothingSent();
});

test('the claude signal raises the overall risk engine verdict for an entitled org', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(claudeJson([
            'risk' => 80,
            'categories' => ['impersonation'],
            'reasons' => ['Executive impersonation'],
        ]), 200),
    ]);

    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);

    $result = RiskEngine::evaluate([
        'sender_email' => 'ceo@partner.com',
        'email_content' => 'Quick favour while travelling.',
    ], $this->org->id);

    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'AI:')))->toBeTrue();
});
