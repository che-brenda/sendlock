<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', Rule::in($this->dialCodes())],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9 ()-]{4,20}$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must accept the Terms & Conditions to create an organization.',
            'phone.regex' => 'Enter a valid phone number (digits only, without the country code).',
        ]);

        // Store the full international number: dial code + local number.
        $fullPhone = $request->country_code.' '.trim($request->phone);

        // Create Organization. It starts on the Free plan but `pending` — the
        // billing gate holds it at the billing page until a package is paid for.
        $organization = Organization::create([
            'organization_name' => $request->organization_name,
            'industry' => $request->industry,
            'email' => $request->email,
            'phone' => $fullPhone,
            'subscription_plan' => 'Free',
            'subscription_status' => 'pending',
            'status' => true,
        ]);

        // Create the organization's founding admin account. Registration is
        // org-centric — there is no personal name here; the account is identified
        // by the organization (its `name` = the organization name). first/last
        // name and worker number are filled in later from User Management.
        $user = User::create([
            'name' => $request->organization_name,
            'email' => $request->email,
            'phone' => $fullPhone,
            'password' => Hash::make($request->password),
            'organization_id' => $organization->id,
            'status' => true,
        ]);

        // Assign Role
        $user->assignRole('Organization Admin');

        event(new Registered($user));

        Auth::login($user);

        // Straight to billing — choose and pay for a package before the dashboard.
        return redirect()->route('billing.index');

    }

    /**
     * Valid international dial codes from config/countries.php.
     *
     * @return array<int, string>
     */
    protected function dialCodes(): array
    {
        return array_values(array_unique(
            array_column(config('countries.list', []), 'dial')
        ));
    }
}
