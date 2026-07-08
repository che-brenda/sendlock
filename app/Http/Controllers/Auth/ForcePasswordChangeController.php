<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * First-sign-in password reset. A user created by an organization admin logs in
 * with a system-issued temporary password and is held here (by the
 * EnsurePasswordChanged middleware) until they choose their own password. Once
 * they do, the temporary credential is wiped from the admin's view.
 */
class ForcePasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        // Nothing to do for an account that isn't on a temporary password.
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-password-change', [
            'organization' => $request->user()->organization,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'temporary_password' => null,
        ]);

        AuditLogger::log(
            'PASSWORD_CHANGE',
            'USER',
            $user->id,
            'Set own password on first sign-in'
        );

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your password has been set. Welcome to SendLock.');
    }
}
