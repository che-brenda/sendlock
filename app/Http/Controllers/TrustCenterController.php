<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\BlockedDomain;
use App\Models\TrustedDomain;
use App\Models\VendorBankAccount;
use App\Models\VerifiedRecipient;
use App\Services\PublicEmailProviders;
use Illuminate\Http\Request;

/**
 * The Trust Center: an organization's centralized trust ecosystem —
 * trusted vendor domains, blocked domains, verified recipients and known
 * vendor banking details. Every query is scoped to the current tenant.
 */
class TrustCenterController extends Controller
{
    private function organizationId(): int
    {
        return auth()->user()->organization_id;
    }

    public function index()
    {
        $organizationId = $this->organizationId();

        $trustedDomains = TrustedDomain::where('organization_id', $organizationId)->latest()->get();
        $blockedDomains = BlockedDomain::where('organization_id', $organizationId)->latest()->get();
        $verifiedRecipients = VerifiedRecipient::where('organization_id', $organizationId)->latest()->get();
        $vendorBankAccounts = VendorBankAccount::where('organization_id', $organizationId)->latest()->get();

        return view('trust-center.index', compact(
            'trustedDomains',
            'blockedDomains',
            'verifiedRecipients',
            'vendorBankAccounts'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Trusted domains
    |--------------------------------------------------------------------------
    */

    public function storeTrustedDomain(Request $request)
    {
        $organizationId = $this->organizationId();

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'vendor_name' => 'nullable|string|max:255',
        ]);

        $raw = strtolower(trim($validated['domain']));

        // A full email address is ADDRESS-level trust, not domain-level. Trusting
        // the whole domain (e.g. gmail.com) would trust every address on it — so a
        // single-letter variant would read as trusted. Store it as a verified
        // contact instead, so EmailIntelligenceService catches look-alikes.
        if (str_contains($raw, '@') && filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            VerifiedRecipient::firstOrCreate(
                ['organization_id' => $organizationId, 'email' => $raw],
                ['name' => $validated['vendor_name'] ?? null, 'verified' => true, 'verified_at' => now()],
            );

            AuditLogger::log('CREATE', 'VERIFIED_RECIPIENT', null, 'Verified contact '.$raw.' (entered in trusted-domain field)');

            return back()->with('info', 'That is a full email address, so it was trusted as a specific verified contact — not the entire domain. Any look-alike of it will now be flagged.');
        }

        $domain = $this->normalizeDomain($validated['domain']);

        // A public email provider cannot be trusted as a whole domain — it would
        // trust every address on it. Direct the user to trust the specific contact.
        if (PublicEmailProviders::is($domain)) {
            return back()->withErrors([
                'domain' => $domain.' is a public email provider — trusting the whole domain would trust everyone on it. Add the specific address (e.g. name@'.$domain.') under Verified Recipients instead.',
            ]);
        }

        $request->merge(['domain' => $domain]);
        $request->validate([
            'domain' => 'unique:trusted_domains,domain,NULL,id,organization_id,'.$organizationId,
        ]);

        TrustedDomain::create([
            'organization_id' => $organizationId,
            'domain' => $domain,
            'vendor_name' => $validated['vendor_name'] ?? null,
            'active' => true,
        ]);

        AuditLogger::log('CREATE', 'TRUSTED_DOMAIN', null, 'Trusted domain '.$domain);

        return back()->with('success', 'Trusted domain added.');
    }

    public function destroyTrustedDomain(TrustedDomain $trustedDomain)
    {
        $this->authorizeTenant($trustedDomain->organization_id);

        $trustedDomain->delete();

        AuditLogger::log('DELETE', 'TRUSTED_DOMAIN', $trustedDomain->id, 'Removed trusted domain '.$trustedDomain->domain);

        return back()->with('success', 'Trusted domain removed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Blocked domains
    |--------------------------------------------------------------------------
    */

    public function storeBlockedDomain(Request $request)
    {
        $organizationId = $this->organizationId();

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        $domain = $this->normalizeDomain($validated['domain']);

        $request->merge(['domain' => $domain]);
        $request->validate([
            'domain' => 'unique:blocked_domains,domain,NULL,id,organization_id,'.$organizationId,
        ]);

        BlockedDomain::create([
            'organization_id' => $organizationId,
            'domain' => $domain,
            'reason' => $validated['reason'] ?? null,
            'active' => true,
        ]);

        AuditLogger::log('CREATE', 'BLOCKED_DOMAIN', null, 'Blocked domain '.$domain);

        return back()->with('success', 'Blocked domain added.');
    }

    public function destroyBlockedDomain(BlockedDomain $blockedDomain)
    {
        $this->authorizeTenant($blockedDomain->organization_id);

        $blockedDomain->delete();

        AuditLogger::log('DELETE', 'BLOCKED_DOMAIN', $blockedDomain->id, 'Removed blocked domain '.$blockedDomain->domain);

        return back()->with('success', 'Blocked domain removed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Verified recipients
    |--------------------------------------------------------------------------
    */

    public function storeVerifiedRecipient(Request $request)
    {
        $organizationId = $this->organizationId();

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $email = strtolower(trim($validated['email']));

        $request->merge(['email' => $email]);
        $request->validate([
            'email' => 'unique:verified_recipients,email,NULL,id,organization_id,'.$organizationId,
        ]);

        VerifiedRecipient::create([
            'organization_id' => $organizationId,
            'email' => $email,
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'verified' => true,
            'verified_at' => now(),
        ]);

        AuditLogger::log('CREATE', 'VERIFIED_RECIPIENT', null, 'Verified recipient '.$email);

        return back()->with('success', 'Verified recipient added.');
    }

    public function destroyVerifiedRecipient(VerifiedRecipient $verifiedRecipient)
    {
        $this->authorizeTenant($verifiedRecipient->organization_id);

        $verifiedRecipient->delete();

        AuditLogger::log('DELETE', 'VERIFIED_RECIPIENT', $verifiedRecipient->id, 'Removed verified recipient '.$verifiedRecipient->email);

        return back()->with('success', 'Verified recipient removed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor bank accounts (baseline for financial-data comparison)
    |--------------------------------------------------------------------------
    */

    public function storeVendorBankAccount(Request $request)
    {
        $organizationId = $this->organizationId();

        $validated = $request->validate([
            'vendor_name' => 'nullable|string|max:255',
            'vendor_domain' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:100',
            'swift' => 'nullable|string|max:50',
            'label' => 'nullable|string|max:255',
        ]);

        VendorBankAccount::create([
            'organization_id' => $organizationId,
            'vendor_name' => $validated['vendor_name'] ?? null,
            'vendor_domain' => $this->normalizeDomain($validated['vendor_domain']),
            'account_number' => $validated['account_number'] ?? null,
            'iban' => $validated['iban'] ?? null,
            'swift' => $validated['swift'] ?? null,
            'label' => $validated['label'] ?? null,
        ]);

        AuditLogger::log('CREATE', 'VENDOR_BANK_ACCOUNT', null, 'Vendor bank account for '.$validated['vendor_domain']);

        return back()->with('success', 'Vendor bank account added.');
    }

    public function destroyVendorBankAccount(VendorBankAccount $vendorBankAccount)
    {
        $this->authorizeTenant($vendorBankAccount->organization_id);

        $vendorBankAccount->delete();

        AuditLogger::log('DELETE', 'VENDOR_BANK_ACCOUNT', $vendorBankAccount->id, 'Removed vendor bank account '.$vendorBankAccount->vendor_domain);

        return back()->with('success', 'Vendor bank account removed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function authorizeTenant(int $ownerOrganizationId): void
    {
        abort_unless($ownerOrganizationId === $this->organizationId(), 403);
    }

    private function normalizeDomain(string $value): string
    {
        $value = strtolower(trim($value));

        // Accept a full email or URL and reduce to the bare domain.
        if (str_contains($value, '@')) {
            $value = substr(strrchr($value, '@'), 1);
        }

        $value = preg_replace('#^https?://#', '', $value);
        $value = preg_replace('#/.*$#', '', $value);

        return $value;
    }
}
