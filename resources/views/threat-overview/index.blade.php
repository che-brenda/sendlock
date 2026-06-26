<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Threat Overview</h2>
            <p class="text-sm text-slate-400">Detections across {{ $totalScans }} scan(s)</p>
        </div>
    </x-slot>

    @php
        $levelStyles = [
            'SAFE' => 'text-emerald-600',
            'LOW' => 'text-lime-600',
            'MEDIUM' => 'text-amber-600',
            'HIGH' => 'text-orange-600',
            'CRITICAL' => 'text-rose-600',
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach($counts as $level => $count)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $level }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $levelStyles[$level] ?? 'text-slate-900' }}">{{ $count }}</p>
                </div>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent High-Risk Detections</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Sender</th>
                                <th class="px-6 py-3">Score</th>
                                <th class="px-6 py-3">Level</th>
                                <th class="px-6 py-3">Decision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($highRisk as $scan)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $scan->created_at->format('M d, H:i') }}</td>
                                <td class="px-6 py-3 text-slate-700">{{ $scan->sender_email }}</td>
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $scan->risk_score }}</td>
                                <td class="px-6 py-3 font-medium {{ $levelStyles[$scan->risk_level] ?? 'text-slate-700' }}">{{ $scan->risk_level }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $scan->decision }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No high-risk detections.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
