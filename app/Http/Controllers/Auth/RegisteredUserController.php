<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\Organization;

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
'first_name' => ['required', 'string', 'max:255'],
'last_name' => ['required', 'string', 'max:255'],
'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
'password' => ['required', 'confirmed', Rules\Password::defaults()],
]);


// Create Organization
$organization = Organization::create([
    'organization_name' => $request->organization_name,
    'industry' => $request->industry,
    'email' => $request->email,
    'subscription_plan' => 'Free',
    'status' => true,
]);

// Create the first admin. Worker number is assigned manually later from
// User Management, so it starts empty for the founding administrator.
$user = User::create([
    'first_name' => $request->first_name,
    'last_name' => $request->last_name,
    'name' => $request->first_name . ' ' . $request->last_name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'organization_id' => $organization->id,
    'status' => true,
]);

// Assign Role
$user->assignRole('Organization Admin');

event(new Registered($user));

Auth::login($user);

return redirect(route('dashboard', absolute: false));


}
}
