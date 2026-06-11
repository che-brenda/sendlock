<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with(['department'])
    ->where(
        'organization_id',
        auth()->user()->organization_id
    )
    ->latest()
    ->get();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
{
    $request->validate([
        'first_name' => 'required|max:255',
        'last_name' => 'required|max:255',
        'email' => 'required|email|unique:users,email',
        'department_id' => 'nullable|exists:departments,id',
        'role' => 'required',
        'password' => [
            'required',
            'confirmed',
            Rules\Password::defaults()
        ],
    ]);

    $user = User::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'name' => $request->first_name . ' ' . $request->last_name,
        'email' => $request->email,
        'password' => Hash::make($request->password),

        'organization_id' => auth()->user()->organization_id,

        'department_id' => $request->department_id,

        'status' => true,
    ]);

    $user->assignRole($request->role);

    return redirect()
        ->route('users.index')
        ->with('success', 'User created successfully.');
   }

   public function edit(User $user)
{
    $user = User::where(
        'organization_id',
        auth()->user()->organization_id
    )->findOrFail($user->id);

    $departments = Department::where(
        'organization_id',
        auth()->user()->organization_id
    )->get();

    $roles = Role::where(
        'name',
        '!=',
        'Super Admin'
    )->get();

    return view(
        'users.edit',
        compact(
            'user',
            'departments',
            'roles'
        )
    );
}

   public function show(User $user)
{
    $user = User::with([
        'department',
        'organization'
    ])
    ->where(
        'organization_id',
        auth()->user()->organization_id
    )
    ->findOrFail($user->id);

    return view('users.show', compact('user'));
}


public function activate(User $user)
{
    $user = User::where(
        'organization_id',
        auth()->user()->organization_id
    )->findOrFail($user->id);

    $user->update([
        'status' => true
    ]);

    return back()->with(
        'success',
        'User activated successfully.'
    );
}

public function deactivate(User $user)
{
    $user = User::where(
        'organization_id',
        auth()->user()->organization_id
    )->findOrFail($user->id);

    if ($user->id === auth()->id()) {

        return back()->withErrors([
            'error' =>
            'You cannot deactivate your own account.'
        ]);

    }

    $user->update([
        'status' => false
    ]);

    return back()->with(
        'success',
        'User deactivated successfully.'
    );
}


public function destroy(User $user)
{
    $user = User::where(
        'organization_id',
        auth()->user()->organization_id
    )->findOrFail($user->id);

    // Prevent self-delete
    if ($user->id === auth()->id()) {
        return back()->withErrors([
            'error' => 'You cannot delete your own account.'
        ]);
    }

    // Prevent deleting Super Admin
    if ($user->hasRole('Super Admin')) {
        return back()->withErrors([
            'error' => 'Super Admin cannot be deleted.'
        ]);
    }

    $user->delete();

    return redirect()
        ->route('users.index')
        ->with('success', 'User deleted successfully.');
}

    public function create()
    {
        $departments = Department::where(
    'organization_id',
    auth()->user()->organization_id
)->get();
        $roles = Role::where('name', '!=', 'Super Admin')->get();

        return view('users.create', compact(
            'departments',
            'roles'
        ));
    }
}