<?php

use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\Ai\ContentClassifier;
use App\Services\Ai\GeminiContentClassifier;
use App\Services\Ai\NullContentClassifier;
use App\Services\RiskEngine;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
});

function geminiJson(array $payload): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($payload)]]],
        ]],
    ];
}

test('the null classifier is the default and adds no signal', function () {
    Http::fake();

    expect(app(ContentClassifier::class))->toBeInstanceOf(NullContentClassifier::class);

    $result = (new NullContentClassifier)->classify('Hi', 'Just checking in');
    expect($result['score'])->toBe(0);

    Http::assertNothingSent();
});

test('gemini classifies fraud content into a capped score and findings', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiJson([
            'risk' => 90,
            'categories' => ['bec', 'invoice_fraud'],
            'reasons' => ['Requests urgent change of bank account', 'Pressure language'],
        ]), 200),
    ]);

    $result = (new GeminiContentClassifier)->classify('Urgent', 'Please change our bank account today.');

    // risk 90 scaled to the 50 cap -> 45.
    expect($result['score'])->toBe(45);
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'AI:')))->toBeTrue();
});

test('gemini empty content makes no call', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);
    Http::fake();

    expect((new GeminiContentClassifier)->classify(null, null)['score'])->toBe(0);
    Http::assertNothingSent();
});

test('a gemini API failure degrades to no signal', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response('error', 500)]);

    expect((new GeminiContentClassifier)->classify('x', 'some content')['score'])->toBe(0);
});

test('malformed gemini JSON degrades to no signal', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'not json']]]]],
        ], 200),
    ]);

    expect((new GeminiContentClassifier)->classify('x', 'some content')['score'])->toBe(0);
});

test('the AI signal raises the overall risk engine verdict', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiJson([
            'risk' => 80,
            'reasons' => ['Executive impersonation detected'],
        ]), 200),
    ]);

    // Trusted sender + benign-looking text the rules miss; the AI catches it.
    TrustedDomain::create(['organization_id' => $this->org->id, 'domain' => 'partner.com', 'active' => true]);

    $benign = RiskEngine::evaluate(['sender_email' => 'ceo@partner.com'], $this->org->id);

    $aiFlagged = RiskEngine::evaluate([
        'sender_email' => 'ceo@partner.com',
        'email_content' => 'Quick favour while I am travelling.',
    ], $this->org->id);

    expect($aiFlagged['risk_score'])->toBeGreaterThan($benign['risk_score']);
    expect(collect($aiFlagged['findings'])->contains(fn ($f) => str_contains($f, 'AI:')))->toBeTrue();
});

test('the default risk engine path makes no AI call', function () {
    Http::fake();

    RiskEngine::evaluate([
        'sender_email' => 'x@unknown.com',
        'email_content' => 'wire transfer please',
    ], $this->org->id);

    Http::assertNothingSent();
});
