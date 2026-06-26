<?php

namespace App\Http\Controllers;

use App\Models\ThreatIntelDomain;
use Illuminate\Http\Request;
use App\Helpers\AuditLogger;

/**
 * Platform-wide threat intelligence list, curated by Super Admins. Shared across
 * all tenants and consumed by {@see \App\Services\ThreatIntelligenceService}.
 * Route-gated to Super Admin via the `superadmin` middleware.
 */
class ThreatIntelController extends Controller
{
    public function index()
    {
        $domains = ThreatIntelDomain::latest()->get();

        return view('threat-intel.index', compact('domains'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'category' => 'nullable|in:phishing,malware,bec,spam',
            'severity' => 'required|in:LOW,MEDIUM,HIGH',
            'notes' => 'nullable|string|max:1000',
        ]);

        $domain = strtolower(trim($validated['domain']));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);

        $request->merge(['domain' => $domain]);
        $request->validate(['domain' => 'unique:threat_intel_domains,domain']);

        ThreatIntelDomain::create([
            'domain' => $domain,
            'category' => $validated['category'] ?? null,
            'severity' => $validated['severity'],
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLogger::log('CREATE', 'THREAT_INTEL_DOMAIN', null, 'Added threat domain ' . $domain);

        return back()->with('success', 'Threat domain added.');
    }

    public function destroy(ThreatIntelDomain $threatIntelDomain)
    {
        $threatIntelDomain->delete();

        AuditLogger::log('DELETE', 'THREAT_INTEL_DOMAIN', $threatIntelDomain->id, 'Removed threat domain ' . $threatIntelDomain->domain);

        return back()->with('success', 'Threat domain removed.');
    }
}
