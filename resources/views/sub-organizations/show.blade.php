<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">{{ $subOrganization->organization_name }}</h2>
            <p class="text-sm text-slate-400">Sub-organization of {{ $head->organization_name }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between">
                <a href="{{ route('sub-organizations.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Back to sub-organizations
                </a>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Read-only view</span>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Users</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $subOrganization->users_count }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Departments</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $subOrganization->departments_count }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Email Scans</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $subOrganization->email_scans_count }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Details</h3>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Industry</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->industry ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Phone</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Subscription Plan</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->subscription_plan }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            @if($subOrganization->status)
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Inactive</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Members (read-only) -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Members</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Name</th>
                                <th class="px-6 py-3">Email</th>
                                <th class="px-6 py-3">Worker #</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($members as $member)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 text-slate-700">{{ $member->name }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $member->email }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $member->worker_number ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $member->status ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $member->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No members yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent email scans (read-only) -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent Email Scans</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Sender</th>
                                <th class="px-6 py-3">Level</th>
                                <th class="px-6 py-3">Decision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentScans as $scan)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $scan->created_at->format('M d, H:i') }}</td>
                                <td class="px-6 py-3 text-slate-700">{{ $scan->sender_email }}</td>
                                <td class="px-6 py-3 font-medium text-slate-700">{{ $scan->risk_level }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $scan->decision }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No scans yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent activity (read-only) -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent Activity</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentLogs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $log->created_at->format('M d, H:i') }}</td>
                                <td class="px-6 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $log->action }}</span></td>
                                <td class="px-6 py-3 text-slate-600">{{ $log->description }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">No activity recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
