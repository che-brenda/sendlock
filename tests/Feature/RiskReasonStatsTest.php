<?php

use App\Models\EmailScan;
use App\Models\Organization;
use App\Services\RiskReasonStats;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->org = Organization::create(['organization_name' => 'Acme', 'type' => 'head', 'status' => true]);
    $this->member = makeUser($this->org, 'Employee');
});

/** Create a scan whose analysis flags a specific reason. */
function scanWithRows(Organization $org, string $level, array $rows): EmailScan
{
    return EmailScan::create([
        'organization_id' => $org->id,
        'user_id' => $org->users()->first()->id,
        'sender_email' => 'x@vendor.test',
        'sender_domain' => 'vendor.test',
        'risk_score' => 75,
        'risk_level' => $level,
        'decision' => 'RECIPIENT_VERIFY',
        'analysis' => ['rows' => $rows],
    ]);
}

test('it tallies primary risk reasons from scan history, ordered by frequency', function () {
    // Two "Similar Domain", one "New Domain", one "Impersonation".
    scanWithRows($this->org, 'HIGH', [['key' => 'similarity', 'status' => 'bad']]);
    scanWithRows($this->org, 'HIGH', [['key' => 'similarity', 'status' => 'bad']]);
    scanWithRows($this->org, 'MEDIUM', [['key' => 'domain_age', 'status' => 'bad']]);
    scanWithRows($this->org, 'CRITICAL', [['key' => 'trusted_address', 'status' => 'bad', 'value' => 'Look-alike — not verified']]);
    // A SAFE scan is counted in the total but is not a "risk reason".
    scanWithRows($this->org, 'SAFE', [['key' => 'trusted_address', 'status' => 'ok', 'value' => 'Verified contact']]);

    $stats = RiskReasonStats::forOrganizations([$this->org->id]);

    expect($stats['total'])->toBe(5)
        ->and($stats['flagged'])->toBe(4);

    // Ordered by count desc → Similar Domain (2) first.
    expect($stats['segments'][0]['label'])->toBe('Similar Domain')
        ->and($stats['segments'][0]['value'])->toBe(2)
        ->and($stats['segments'][0]['color'])->toBe(RiskReasonStats::COLORS['Similar Domain']);

    $byLabel = collect($stats['segments'])->keyBy('label');
    expect($byLabel['New Domain']['value'])->toBe(1)
        ->and($byLabel['Impersonation']['value'])->toBe(1)
        ->and($byLabel->has('Similar Domain'))->toBeTrue();
});

test('impersonation takes priority over a similar-domain flag on the same scan', function () {
    scanWithRows($this->org, 'HIGH', [
        ['key' => 'trusted_address', 'status' => 'bad', 'value' => 'Look-alike — not verified'],
        ['key' => 'similarity', 'status' => 'bad'],
    ]);

    $stats = RiskReasonStats::forOrganizations([$this->org->id]);

    expect($stats['segments'])->toHaveCount(1)
        ->and($stats['segments'][0]['label'])->toBe('Impersonation');
});

test('an org with no flagged scans yields empty segments', function () {
    scanWithRows($this->org, 'SAFE', [['key' => 'trusted_address', 'status' => 'ok']]);
    scanWithRows($this->org, 'LOW', [['key' => 'trusted_address', 'status' => 'ok']]);

    $stats = RiskReasonStats::forOrganizations([$this->org->id]);

    expect($stats['segments'])->toBe([])
        ->and($stats['total'])->toBe(2)
        ->and($stats['flagged'])->toBe(0);
});

test('the dashboard renders the data-driven risk chart', function () {
    scanWithRows($this->org, 'HIGH', [['key' => 'similarity', 'status' => 'bad']]);
    $admin = makeUser($this->org, 'Organization Admin');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Top Risk Reasons')
        ->assertSee('rc-segments', false);
});
