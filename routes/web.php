<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmailScanController;
use App\Http\Controllers\FlaggedDomainController;
use App\Http\Controllers\OrganizationManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProtectedEmailController;
use App\Http\Controllers\RecipientVerificationController;
use App\Http\Controllers\SecurityInsightsController;
use App\Http\Controllers\SubOrganizationController;
use App\Http\Controllers\ThreatIntelController;
use App\Http\Controllers\TrustCenterController;
use App\Http\Controllers\UserManagementController;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    if (auth()->user()->hasRole('Super Admin')) {
        $organizations = Organization::count();
        $users = User::count();
        $departments = Department::count();
        $activeUsers = User::where('status', true)->count();
        $inactiveUsers = User::where('status', false)->count();
        $recentLogs = AuditLog::latest()->take(10)->get();
    } else {
        $organizationId = auth()->user()->organization_id;
        $organizations = 1;
        $users = User::where('organization_id', $organizationId)->count();
        $departments = Department::where('organization_id', $organizationId)->count();
        $activeUsers = User::where('organization_id', $organizationId)->where('status', true)->count();
        $inactiveUsers = User::where('organization_id', $organizationId)->where('status', false)->count();
        $recentLogs = AuditLog::where('organization_id', $organizationId)->latest()->take(10)->get();
    }

return view('dashboard', compact('organizations', 'users', 'departments', 'activeUsers', 'inactiveUsers', 'recentLogs'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/email-scans', [EmailScanController::class, 'index'])->name('email-scans.index');
    Route::post('/email-scans/analyze', [EmailScanController::class, 'analyze'])->name('email-scans.analyze');

    // Auto-flagged impersonation / untrusted domains: review register + the
    // "request manager authorization" escalation from the popup warning.
    Route::get('/flagged-domains', [FlaggedDomainController::class, 'index'])->name('flagged-domains.index');
    Route::post('/flagged-domains/request-approval', [FlaggedDomainController::class, 'requestApproval'])->name('flagged-domains.request-approval');
});

Route::post(
    '/users/{user}/activate',
    [UserManagementController::class, 'activate']
)->name('users.activate');

Route::post(
    '/users/{user}/deactivate',
    [UserManagementController::class, 'deactivate']
)->name('users.deactivate');

Route::middleware(['auth'])->group(function () {

    Route::resource('departments', DepartmentController::class);
    Route::resource('users', UserManagementController::class);

});

Route::get('/super-admin-test', function () {
    return 'Super Admin Middleware Works';
})->middleware(['auth', 'superadmin']);

Route::middleware(['auth', 'superadmin'])->group(function () {

    Route::resource(
        'organizations',
        OrganizationManagementController::class
    );

    // Phase 4 — platform-wide threat intelligence list.
    Route::get('/threat-intel', [ThreatIntelController::class, 'index'])->name('threat-intel.index');
    Route::post('/threat-intel', [ThreatIntelController::class, 'store'])->name('threat-intel.store');
    Route::delete('/threat-intel/{threatIntelDomain}', [ThreatIntelController::class, 'destroy'])->name('threat-intel.destroy');

});

/*
|--------------------------------------------------------------------------
| Head Organization Admin — sub-organization management
|--------------------------------------------------------------------------
| A head organization owns sub-organizations; these are scoped to the
| current head org's tree inside the controller.
*/
Route::middleware(['auth', 'headorg.admin'])->group(function () {

    Route::get('/sub-organizations', [SubOrganizationController::class, 'index'])
        ->name('sub-organizations.index');

    Route::get('/sub-organizations/create', [SubOrganizationController::class, 'create'])
        ->name('sub-organizations.create');

    Route::post('/sub-organizations', [SubOrganizationController::class, 'store'])
        ->name('sub-organizations.store');

    Route::get('/sub-organizations/{subOrganization}', [SubOrganizationController::class, 'show'])
        ->name('sub-organizations.show');

});

/*
|--------------------------------------------------------------------------
| Protected send + verification + approvals (Phase 3)
|--------------------------------------------------------------------------
| The outbound differentiator: risk-score a send, verify the recipient
| (SMS/WhatsApp/email), and require approval before release. All actions are
| tenant-scoped; approval is additionally role-gated inside the controller.
*/
Route::middleware(['auth'])->group(function () {

    // Compose & submit a protected outbound email.
    Route::get('/send-protected', [ProtectedEmailController::class, 'create'])->name('protected-email.create');
    Route::post('/send-protected', [ProtectedEmailController::class, 'store'])->name('protected-email.store');
    Route::get('/send-protected/{approvalRequest}', [ProtectedEmailController::class, 'show'])->name('protected-email.show');

    // Recipient Verification Center.
    Route::get('/recipient-verification', [RecipientVerificationController::class, 'index'])->name('recipient-verification.index');
    Route::post('/recipient-verification/{approvalRequest}/send', [RecipientVerificationController::class, 'send'])->name('recipient-verification.send');
    Route::post('/recipient-verification/{approvalRequest}/verify', [RecipientVerificationController::class, 'verify'])->name('recipient-verification.verify');

    // Approvals queue.
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

});

/*
|--------------------------------------------------------------------------
| Trust Center (Phase 2) — tenant trust ecosystem
|--------------------------------------------------------------------------
| Managing trust lists is an administrative action, so it is gated to
| organization-administration roles. All actions are tenant-scoped.
*/
Route::middleware(['auth', 'org.admin'])->group(function () {

    Route::get('/trust-center', [TrustCenterController::class, 'index'])
        ->name('trust-center.index');

    Route::post('/trust-center/trusted-domains', [TrustCenterController::class, 'storeTrustedDomain'])
        ->name('trust-center.trusted-domains.store');
    Route::delete('/trust-center/trusted-domains/{trustedDomain}', [TrustCenterController::class, 'destroyTrustedDomain'])
        ->name('trust-center.trusted-domains.destroy');

    Route::post('/trust-center/blocked-domains', [TrustCenterController::class, 'storeBlockedDomain'])
        ->name('trust-center.blocked-domains.store');
    Route::delete('/trust-center/blocked-domains/{blockedDomain}', [TrustCenterController::class, 'destroyBlockedDomain'])
        ->name('trust-center.blocked-domains.destroy');

    Route::post('/trust-center/verified-recipients', [TrustCenterController::class, 'storeVerifiedRecipient'])
        ->name('trust-center.verified-recipients.store');
    Route::delete('/trust-center/verified-recipients/{verifiedRecipient}', [TrustCenterController::class, 'destroyVerifiedRecipient'])
        ->name('trust-center.verified-recipients.destroy');

    Route::post('/trust-center/vendor-bank-accounts', [TrustCenterController::class, 'storeVendorBankAccount'])
        ->name('trust-center.vendor-bank-accounts.store');
    Route::delete('/trust-center/vendor-bank-accounts/{vendorBankAccount}', [TrustCenterController::class, 'destroyVendorBankAccount'])
        ->name('trust-center.vendor-bank-accounts.destroy');

});

/*
|--------------------------------------------------------------------------
| Placeholder routes for features scheduled in later phases
|--------------------------------------------------------------------------
| These keep every menu in the UI navigable now. Each renders a styled
| "planned" page describing the phase it ships in. Replace with real
| controllers as the corresponding phase is built.
*/
Route::middleware(['auth'])->group(function () {

    $placeholder = fn (string $title, string $phase, string $summary) => view('placeholder', compact('title', 'phase', 'summary'));

    Route::get('/billing', fn () => $placeholder(
        'Plans & Billing',
        'Phase 1+',
        'Subscription plans, seat counts and invoicing for head organizations and their sub-organizations.'
    ))->name('billing.index');

    // Phase 4 — security insight pages derived from scan history.
    Route::get('/threat-overview', [SecurityInsightsController::class, 'threatOverview'])
        ->name('threat.overview');
    Route::get('/blocked-attempts', [SecurityInsightsController::class, 'blockedAttempts'])
        ->name('blocked-attempts.index');

    Route::get('/policies', fn () => $placeholder(
        'Policies',
        'Phase 2',
        'Outbound protection levels, block-on-similar-domain, and approval requirements per organization.'
    ))->name('policies.index');

    Route::get('/reports', fn () => $placeholder(
        'Reports',
        'Phase 2',
        'Exportable reporting on scans, blocked attempts, verifications and approvals.'
    ))->name('reports.index');

});

require __DIR__.'/auth.php';
