<?php

use App\Models\Organization;
use App\Services\RiskEngine;
use Illuminate\Support\Facades\Http;

function orgOnPlan(string $plan): Organization
{
    return Organization::create([
        'organization_name' => 'Acme',
        'type' => 'head',
        'status' => true,
        'subscription_plan' => $plan,
    ]);
}

test('plan feature entitlements follow the config map', function () {
    expect(orgOnPlan('free')->hasFeature('ai_classification'))->toBeFalse();
    expect(orgOnPlan('beta')->hasFeature('ai_classification'))->toBeTrue();
    expect(orgOnPlan('pro')->hasFeature('sms_verification'))->toBeTrue();
    expect(orgOnPlan('enterprise')->hasFeature('anything_at_all'))->toBeTrue();
});

test('plan matching is case-insensitive and unknown plans fall back to default', function () {
    expect(orgOnPlan('PRO')->hasFeature('ai_classification'))->toBeTrue();
    expect(orgOnPlan('mystery')->hasFeature('ai_classification'))->toBeFalse();
});

test('a free-plan org never triggers the AI provider even when configured', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);
    Http::fake();

    $org = orgOnPlan('free');

    $result = RiskEngine::evaluate([
        'sender_email' => 'x@unknown.com',
        'email_content' => 'urgent wire transfer to a new bank account',
    ], $org->id);

    Http::assertNothingSent();
    expect(collect($result['findings'])->contains(fn ($f) => str_contains($f, 'AI:')))->toBeFalse();
});

test('an entitled org does trigger the AI provider when configured', function () {
    config(['sendlock.ai.driver' => 'gemini']);
    config(['sendlock.ai.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['risk' => 70, 'reasons' => ['fraud']])]]]]],
        ], 200),
    ]);

    $org = orgOnPlan('pro');

    RiskEngine::evaluate([
        'sender_email' => 'x@unknown.com',
        'email_content' => 'please pay this invoice',
    ], $org->id);

    Http::assertSentCount(1);
});
