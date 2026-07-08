<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use Illuminate\Support\Carbon;

/**
 * Security Center — the assurance page that shows users the platform's live
 * security posture (which protections are active) and the firewall's blocked-
 * attack tally. Super Admins additionally see recent blocked requests.
 */
class SecurityController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $blocked30 = SecurityEvent::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $blockedTotal = SecurityEvent::count();

        $recent = $user->isSuperAdmin()
            ? SecurityEvent::latest('created_at')->take(12)->get()
            : collect();

        return view('security.index', [
            'firewallOn' => (bool) config('firewall.enabled', true),
            'blocked30' => $blocked30,
            'blockedTotal' => $blockedTotal,
            'recent' => $recent,
        ]);
    }
}
