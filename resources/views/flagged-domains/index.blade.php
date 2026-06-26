<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Flagged Domains</h2>
            <p class="text-sm text-slate-400">Impersonation &amp; untrusted domains the engine recorded automatically</p>
        </div>
    </x-slot>

    @php
        $typeStyles = [
            'lookalike' => 'bg-rose-100 text-rose-700 border-rose-200',
            'homograph' => 'bg-rose-100 text-rose-700 border-rose-200',
            'typosquat' => 'bg-orange-100 text-orange-700 border-orange-200',
            'subdomain_abuse' => 'bg-orange-100 text-orange-700 border-orange-200',
            'disposable' => 'bg-amber-100 text-amber-700 border-amber-200',
            'suspicious_tld' => 'bg-amber-100 text-amber-700 border-amber-200',
            'entropy' => 'bg-amber-100 text-amber-700 border-amber-200',
            'untrusted' => 'bg-amber-100 text-amber-700 border-amber-200',
        ];
        $typeLabels = [
            'lookalike' => 'Lookalike',
            'homograph' => 'Homograph',
            'typosquat' => 'Typosquat',
            'subdomain_abuse' => 'Subdomain abuse',
            'disposable' => 'Disposable',
            'suspicious_tld' => 'High-risk TLD',
            'entropy' => 'Random-looking',
            'untrusted' => 'Untrusted',
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recorded Domains</h3>
                    <p class="text-sm text-slate-400">Domains used more than once trigger a popup warning. Promote to the Blocked list in the Trust Center to hard-block.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Domain</th>
                                <th class="px-6 py-3">Detection</th>
                                <th class="px-6 py-3">Reason</th>
                                <th class="px-6 py-3">Times Seen</th>
                                <th class="px-6 py-3">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($flaggedDomains as $flagged)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-mono font-medium text-slate-800">{{ $flagged->domain }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $typeStyles[$flagged->detection_type] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        {{ $typeLabels[$flagged->detection_type] ?? ucfirst($flagged->detection_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-slate-600">{{ $flagged->reason }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $flagged->times_seen }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $flagged->last_seen_at?->format('M d, H:i') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No flagged domains yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
