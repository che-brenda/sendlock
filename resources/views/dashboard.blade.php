<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Security Dashboard</h2>
            <p class="text-sm text-slate-400">Communication trust overview</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            <!-- Stat cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                @php
                    $cards = [
                        ['label' => 'Organizations', 'value' => $organizations, 'accent' => 'text-slate-900', 'ring' => 'bg-slate-100 text-slate-600'],
                        ['label' => 'Users', 'value' => $users, 'accent' => 'text-slate-900', 'ring' => 'bg-teal-100 text-teal-600'],
                        ['label' => 'Departments', 'value' => $departments, 'accent' => 'text-slate-900', 'ring' => 'bg-indigo-100 text-indigo-600'],
                        ['label' => 'Active Users', 'value' => $activeUsers, 'accent' => 'text-emerald-600', 'ring' => 'bg-emerald-100 text-emerald-600'],
                        ['label' => 'Inactive Users', 'value' => $inactiveUsers, 'accent' => 'text-rose-600', 'ring' => 'bg-rose-100 text-rose-600'],
                    ];
                @endphp

                @foreach($cards as $card)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $card['accent'] }}">{{ $card['value'] }}</p>
                </div>
                @endforeach
            </div>

            <!-- Recent security activity -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent Security Activity</h3>
                    <a href="{{ route('audit-logs.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">View all</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentLogs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $log->action }}</span>
                                </td>
                                <td class="px-6 py-3 text-slate-700">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $log->description }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-slate-400">No activity recorded yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
