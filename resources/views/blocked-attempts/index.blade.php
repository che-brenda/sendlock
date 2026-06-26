<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Blocked Attempts</h2>
            <p class="text-sm text-slate-400">Quarantined and critical-risk detections</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Sender</th>
                                <th class="px-6 py-3">Domain</th>
                                <th class="px-6 py-3">Score</th>
                                <th class="px-6 py-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($blocked as $scan)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $scan->created_at->format('M d, H:i') }}</td>
                                <td class="px-6 py-3 text-slate-700">{{ $scan->sender_email }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $scan->sender_domain }}</td>
                                <td class="px-6 py-3 font-medium text-rose-600">{{ $scan->risk_score }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $scan->is_blocked_domain ? 'Blocked domain' : ($scan->decision === 'QUARANTINE' ? 'Quarantined' : 'Critical risk') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No blocked attempts.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($blocked->hasPages())
            <div>{{ $blocked->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
