<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Audit Log</h2>
            <p class="text-sm text-slate-400">A chronological record of every mutating action in your organization</p>
        </div>
    </x-slot>

    @php
        $isSuper = auth()->user()->hasRole('Super Admin');

        // Color-coded badge per action category.
        $actionStyles = [
            'CREATE'                   => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'UPDATE'                   => 'bg-sky-100 text-sky-700 border-sky-200',
            'DELETE'                   => 'bg-rose-100 text-rose-700 border-rose-200',
            'ACTIVATE'                 => 'bg-teal-100 text-teal-700 border-teal-200',
            'DEACTIVATE'               => 'bg-amber-100 text-amber-700 border-amber-200',
            'SCAN'                     => 'bg-violet-100 text-violet-700 border-violet-200',
            'PROTECTED_SEND'           => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'FLAGGED_DOMAIN_ESCALATION'=> 'bg-orange-100 text-orange-700 border-orange-200',
        ];

        $actionLabels = [
            'PROTECTED_SEND'            => 'Protected Send',
            'FLAGGED_DOMAIN_ESCALATION' => 'Escalation',
        ];

        // A soft, deterministic avatar tint per user.
        $avatarTints = [
            'bg-teal-500', 'bg-violet-500', 'bg-sky-500', 'bg-amber-500',
            'bg-rose-500', 'bg-indigo-500', 'bg-emerald-500',
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Activity</h3>
                        <p class="text-sm text-slate-400">{{ $logs->total() }} {{ \Illuminate\Support\Str::plural('event', $logs->total()) }} recorded</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">When</th>
                                <th class="px-6 py-3">User</th>
                                @if($isSuper)<th class="px-6 py-3">Organization</th>@endif
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">Entity</th>
                                <th class="px-6 py-3">Details</th>
                                <th class="px-6 py-3">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($logs as $log)
                            @php
                                $name = $log->user?->name ?? 'System';
                                $initial = strtoupper(mb_substr($name, 0, 1));
                                $tint = $avatarTints[($log->user_id ?? 0) % count($avatarTints)];
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-medium text-slate-700">{{ $log->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-slate-400">{{ $log->created_at->format('H:i') }} · {{ $log->created_at->diffForHumans(null, true) }} ago</div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $tint }} text-xs font-semibold text-white">
                                            {{ $initial }}
                                        </span>
                                        <span class="font-medium text-slate-700">{{ $name }}</span>
                                    </div>
                                </td>

                                @if($isSuper)
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">
                                    {{ $log->organization?->organization_name ?? '—' }}
                                </td>
                                @endif

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $actionStyles[$log->action] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        {{ $actionLabels[$log->action] ?? \Illuminate\Support\Str::headline(strtolower($log->action)) }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    @if($log->entity_type)
                                    <span class="font-mono text-xs text-slate-500">
                                        {{ $log->entity_type }}{{ $log->entity_id ? ' #' . $log->entity_id : '' }}
                                    </span>
                                    @else
                                    <span class="text-slate-300">—</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-slate-600">
                                    <span class="line-clamp-2">{{ $log->description ?: '—' }}</span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="font-mono text-xs text-slate-400">{{ $log->ip_address ?? '—' }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $isSuper ? 7 : 6 }}" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                        </span>
                                        <p class="mt-3 text-sm font-medium text-slate-600">No activity yet</p>
                                        <p class="mt-1 text-sm text-slate-400">Actions like creating users, scanning email, and managing domains will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($logs->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $logs->links() }}
                </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
