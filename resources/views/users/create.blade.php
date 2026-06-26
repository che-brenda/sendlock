<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Add User</h2>
            <p class="text-sm text-slate-400">Create a new member of your organization</p>
        </div>
    </x-slot>

    @php
        $field = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

                @if($errors->any())
                <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Profile</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">First name</label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}" required class="{{ $field }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Last name</label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" required class="{{ $field }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Job title</label>
                                <input type="text" name="job_title" value="{{ old('job_title') }}" placeholder="e.g. Finance Manager" class="{{ $field }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Worker number</label>
                                <input type="text" name="worker_number" value="{{ old('worker_number') }}" required placeholder="e.g. EMP-1042" class="{{ $field }}">
                                <p class="mt-1 text-xs text-slate-400">Your organization's own staff ID. Must be unique within your organization.</p>
                            </div>
                            <div>
                                <label class="{{ $label }}">Department</label>
                                <select name="department_id" class="{{ $field }}">
                                    <option value="">Select department</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Contact &amp; access</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" required class="{{ $field }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Phone</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="Optional" class="{{ $field }}">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">Role</label>
                                <select name="role" required class="{{ $field }}">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Password</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">Password</label>
                                <input type="password" name="password" required autocomplete="new-password" class="{{ $field }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Confirm password</label>
                                <input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $field }}">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                        <a href="{{ route('users.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
                        <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
