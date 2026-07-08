<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Users &amp; Workers</h2>
            <p class="text-sm text-slate-400">Manage your organization's people and access</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ $users->count() }} user(s)</p>
                <a href="{{ route('users.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add User
                </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Worker #</th>
                                <th class="px-6 py-3">Contact</th>
                                <th class="px-6 py-3">Department</th>
                                <th class="px-6 py-3">Role</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($users as $user)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-slate-400">#{{ $user->id }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xs font-semibold text-white">
                                            {{ $user->initials }}
                                        </span>
                                        <div>
                                            <p class="font-medium text-slate-800">{{ $user->display_name }}</p>
                                            <p class="text-xs text-slate-400">{{ $user->job_title ?? '—' }}</p>
                                            <x-temporary-password :user="$user" class="mt-1.5" />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ $user->worker_number ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <p class="text-slate-700">{{ $user->email }}</p>
                                    <p class="text-xs text-slate-400">{{ $user->phone ?? 'No phone' }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $user->department?->department_name ?? 'Not assigned' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $user->getRoleNames()->first() ?? '—' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($user->status)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('users.show', $user->id) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100">View</a>
                                        <a href="{{ route('users.edit', $user->id) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-teal-600 hover:bg-teal-50">Edit</a>
                                        @if($user->status)
                                        <form action="{{ route('users.deactivate', $user->id) }}" method="POST">@csrf
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-amber-600 hover:bg-amber-50">Deactivate</button>
                                        </form>
                                        @else
                                        <form action="{{ route('users.activate', $user->id) }}" method="POST">@csrf
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50">Activate</button>
                                        </form>
                                        @endif
                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="px-6 py-10 text-center text-slate-400">No users found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
