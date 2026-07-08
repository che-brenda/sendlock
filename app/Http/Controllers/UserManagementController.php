<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

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

    public function store(Request $request)
    {
        $organizationId = auth()->user()->organization_id;

        $request->validate([
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'job_title' => 'nullable|string|max:255',
            // Worker number is entered manually and is unique within the organization.
            'worker_number' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'worker_number')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'role' => 'required',
        ]);

        if (
            ! auth()->user()->hasRole('Super Admin')
            && $request->role === 'Super Admin'
        ) {
            abort(403, 'Unauthorized role assignment.');
        }

        // The admin no longer sets a password. The system issues a strong
        // temporary one that the new user must replace on first sign-in; it is
        // kept (encrypted) on the user record so the admin can read it off their
        // dashboard until it is used.
        $temporaryPassword = Str::password(14);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name.' '.$request->last_name,
            'job_title' => $request->job_title,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($temporaryPassword),
            'organization_id' => $organizationId,
            'worker_number' => $request->worker_number,
            'department_id' => $request->department_id,
            'status' => true,
            'must_change_password' => true,
            'temporary_password' => $temporaryPassword,
        ]);

        $user->assignRole($request->role);

        AuditLogger::log(
            'CREATE',
            'USER',
            $user->id,
            'Created user '.$user->email
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User created. Share their temporary password (shown below) — they must change it on first sign-in.');
    }

    public function show(User $user)
    {
        $user = User::with([
            'department',
            'organization',
        ])
            ->where(
                'organization_id',
                auth()->user()->organization_id
            )
            ->findOrFail($user->id);

        return view('users.show', compact('user'));
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

    public function update(Request $request, User $user)
    {
        $user = User::where(
            'organization_id',
            auth()->user()->organization_id
        )->findOrFail($user->id);

        $request->validate([
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'job_title' => 'nullable|string|max:255',
            'worker_number' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'worker_number')
                    ->where(fn ($query) => $query->where('organization_id', $user->organization_id))
                    ->ignore($user->id),
            ],
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'role' => 'nullable|exists:roles,name',
        ]);

        // Guard the privileged role: only a Super Admin may grant Super Admin,
        // and an existing Super Admin's role must not be downgraded here.
        if (
            $request->filled('role')
            && $request->role === 'Super Admin'
            && ! auth()->user()->hasRole('Super Admin')
        ) {
            abort(403, 'Unauthorized role assignment.');
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name.' '.$request->last_name,
            'job_title' => $request->job_title,
            'worker_number' => $request->worker_number,
            'email' => $request->email,
            'phone' => $request->phone,
            'department_id' => $request->department_id,
        ]);

        if ($request->filled('role') && ! $user->hasRole('Super Admin')) {
            $user->syncRoles([$request->role]);
        }

        AuditLogger::log(
            'UPDATE',
            'USER',
            $user->id,
            'Updated user '.$user->email
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function activate(User $user)
    {
        $user = User::where(
            'organization_id',
            auth()->user()->organization_id
        )->findOrFail($user->id);

        if ($user->hasRole('Super Admin')) {
            return back()->withErrors([
                'error' => 'Super Admin accounts cannot be managed.',
            ]);
        }

        $user->update([
            'status' => true,
        ]);

        AuditLogger::log(
            'ACTIVATE',
            'USER',
            $user->id,
            'Activated user '.$user->email
        );

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
                'error' => 'You cannot deactivate your own account.',
            ]);
        }

        if ($user->hasRole('Super Admin')) {
            return back()->withErrors([
                'error' => 'Super Admin accounts cannot be managed.',
            ]);
        }

        $user->update([
            'status' => false,
        ]);

        AuditLogger::log(
            'DEACTIVATE',
            'USER',
            $user->id,
            'Deactivated user '.$user->email
        );

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

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'error' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole('Super Admin')) {
            return back()->withErrors([
                'error' => 'Super Admin cannot be deleted.',
            ]);
        }

        AuditLogger::log(
            'DELETE',
            'USER',
            $user->id,
            'Deleted user '.$user->email
        );

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
