<?php

use App\Models\Organization;
use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflow;
use App\Services\Verification\VerificationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
    $user = \App\Models\User::factory()->create(['organization_id' => $this->org->id, 'status' => true]);

    $this->request = (new ApprovalWorkflow())->createFromEvaluation(
        ['risk_score' => 75, 'risk_level' => 'HIGH', 'decision' => 'RECIPIENT_VERIFY'],
        ['recipient_email' => 'vendor@partner.com', 'subject' => 'Invoice', 'email_content' => 'Body'],
        $this->org->id,
        $user->id
    );
});

test('the twilio driver sends an SMS via the twilio api', function () {
    config()->set('sendlock.verification.driver', 'twilio');
    config()->set('sendlock.verification.twilio.sid', 'AC_test');
    config()->set('sendlock.verification.twilio.token', 'secret');
    config()->set('sendlock.verification.twilio.sms_from', '+15550001111');

    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_test'], 201)]);

    (new VerificationService())->issue($this->request, 'sms', '+15557654321');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.twilio.com')
            && $request['To'] === '+15557654321'
            && $request['From'] === '+15550001111'
            && str_contains($request['Body'], 'verification code');
    });
});

test('the twilio driver uses the whatsapp prefix in whatsapp mode', function () {
    config()->set('sendlock.verification.driver', 'twilio');
    config()->set('sendlock.verification.twilio.sid', 'AC_test');
    config()->set('sendlock.verification.twilio.token', 'secret');
    config()->set('sendlock.verification.twilio.whatsapp_from', '+15550002222');

    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_test'], 201)]);

    (new VerificationService())->issue($this->request, 'whatsapp', '+15557654321');

    Http::assertSent(fn ($request) => $request['To'] === 'whatsapp:+15557654321'
        && $request['From'] === 'whatsapp:+15550002222');
});

test('twilio falls back to the log stub when credentials are missing', function () {
    config()->set('sendlock.verification.driver', 'twilio');
    config()->set('sendlock.verification.twilio.sid', null);

    Http::fake();

    // Should not throw and should not hit the Twilio API.
    (new VerificationService())->issue($this->request, 'sms', '+15557654321');

    Http::assertNothingSent();
});

test('the default log driver never calls twilio', function () {
    Http::fake();

    (new VerificationService())->issue($this->request, 'sms', '+15557654321');

    Http::assertNothingSent();
});
