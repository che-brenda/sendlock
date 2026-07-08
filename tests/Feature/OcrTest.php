<?php

use App\Models\EmailScan;
use App\Models\Organization;
use App\Models\TrustedDomain;
use App\Services\Ocr\NullOcrDriver;
use App\Services\Ocr\OcrDriver;
use App\Services\Ocr\OcrService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create([
        'organization_name' => 'Acme Corp',
        'type' => 'head',
        'status' => true,
    ]);
});

/**
 * A test OCR driver that returns canned text for any file — stands in for the
 * Tesseract binary so the suite stays offline.
 */
function fakeOcr(string $text): OcrDriver
{
    return new class($text) implements OcrDriver
    {
        public function __construct(private string $text) {}

        public function extract(string $absolutePath): string
        {
            return $this->text;
        }
    };
}

test('the null driver is the default and extracts nothing', function () {
    $service = app(OcrService::class);

    expect($service->enabled())->toBeFalse();
    expect(app(OcrDriver::class))->toBeInstanceOf(NullOcrDriver::class);
});

test('the ocr service returns empty for a non-existent file', function () {
    $service = new OcrService(fakeOcr('some text'));

    expect($service->extract('/no/such/file.png'))->toBe('');
});

test('an uploaded attachment is OCR-scanned and its text influences the verdict', function () {
    $this->app->instance(OcrDriver::class, fakeOcr('Please change of bank account for the next wire transfer.'));

    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $user = makeUser($this->org, 'Employee');

    // Trusted sender + benign typed body, but the uploaded "image" carries fraud
    // language — OCR should surface it and raise the score.
    $response = $this->actingAs($user)->post(route('email-scans.analyze'), [
        'sender_email' => 'hello@partner.com',
        'email_content' => 'See attached.',
        'attachment_file' => UploadedFile::fake()->create('invoice.png', 10, 'image/png'),
    ]);

    $response->assertRedirect();

    // Findings are now persisted on the scan and shown on the risk-analysis page.
    $scan = EmailScan::where('organization_id', $this->org->id)->latest('id')->first();
    $findings = $scan->findings;
    expect(collect($findings)->contains(fn ($f) => str_contains($f, 'OCR')))->toBeTrue();
    expect(collect($findings)->contains(fn ($f) => str_contains($f, 'bank account')))->toBeTrue();
    expect($scan->risk_score)->toBeGreaterThan(0);
});

test('a scan with no uploaded file is unaffected by OCR', function () {
    $this->app->instance(OcrDriver::class, fakeOcr('SHOULD NOT APPEAR'));

    TrustedDomain::create([
        'organization_id' => $this->org->id,
        'domain' => 'partner.com',
        'active' => true,
    ]);

    $user = makeUser($this->org, 'Employee');

    $this->actingAs($user)->post(route('email-scans.analyze'), [
        'sender_email' => 'hello@partner.com',
        'email_content' => 'All good here.',
    ]);

    expect(collect(session('findings'))->contains(fn ($f) => str_contains($f, 'OCR')))->toBeFalse();
});
