<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Domain Risk Analysis</h2>
            <p class="text-sm text-slate-400">Automated verdict for {{ $scan->sender_email }}</p>
        </div>
    </x-slot>

    <x-flagged-domain-warning />

    @php
        $score = (int) $scan->risk_score;
        $level = $scan->risk_level;
        $rows = $scan->analysis['rows'] ?? [];
        $similar = $scan->analysis['similar_trusted'] ?? null;
        $suggestion = $scan->analysis['suggestion'] ?? null;

        // Gauge geometry — a 180° arc filled proportionally to the score.
        $arcColor = [
            'SAFE' => '#10b981', 'LOW' => '#84cc16', 'MEDIUM' => '#f59e0b',
            'HIGH' => '#f97316', 'CRITICAL' => '#ef4444',
        ][$level] ?? '#64748b';
        $arcLen = 251.33;                       // π × r (r = 80)
        $offset = $arcLen * (1 - min(1, max(0, $score / 100)));

        $valueColor = ['ok' => 'text-emerald-600', 'warn' => 'text-amber-600', 'bad' => 'text-rose-600', 'unknown' => 'text-slate-400'];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if($suggestion)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.008v.008H12v-.008Z" /></svg>
                <p class="text-sm text-amber-800">
                    <span class="font-semibold">Address not found in your verified contacts.</span>
                    Did you mean <span class="font-mono font-semibold text-amber-900">{{ $suggestion }}</span>?
                    This sender is a close look-alike — treat it as untrusted until confirmed out-of-band.
                </p>
            </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-2">

                    <!-- Gauge -->
                    <div class="flex flex-col items-center justify-center border-slate-100 md:border-r md:pr-8">
                        <svg viewBox="0 0 200 120" class="w-64">
                            <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#e2e8f0" stroke-width="16" stroke-linecap="round" />
                            <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="{{ $arcColor }}" stroke-width="16" stroke-linecap="round"
                                  stroke-dasharray="{{ $arcLen }}" stroke-dashoffset="{{ $offset }}" />
                            <text x="100" y="88" text-anchor="middle" class="fill-slate-900" style="font-size:34px;font-weight:800;">{{ $score }}</text>
                            <text x="100" y="108" text-anchor="middle" class="fill-slate-400" style="font-size:12px;">/ 100</text>
                        </svg>
                        <p class="-mt-1 text-sm font-semibold uppercase tracking-wide text-slate-400">Risk Score</p>
                        <p class="mt-3 font-mono text-sm text-slate-700">{{ $scan->sender_email }}</p>
                        <span class="mt-2 inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold"
                              style="background: {{ $arcColor }}1a; color: {{ $arcColor }};">{{ $level }}</span>
                    </div>

                    <!-- Signal checklist -->
                    <div class="space-y-1">
                        @forelse($rows as $row)
                        @php $st = $row['status']; @endphp
                        <div class="flex items-center justify-between border-b border-slate-50 py-2.5 last:border-0">
                            <div class="flex items-center gap-2.5">
                                <span @class([
                                    'flex h-5 w-5 shrink-0 items-center justify-center rounded',
                                    'bg-emerald-100 text-emerald-600' => $st === 'ok',
                                    'bg-rose-100 text-rose-600' => $st === 'bad',
                                    'bg-amber-100 text-amber-600' => $st === 'warn',
                                    'bg-slate-100 text-slate-400' => $st === 'unknown',
                                ])>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        @if($st === 'ok')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        @elseif($st === 'bad')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        @elseif($st === 'warn')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01" />
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v.01" />
                                        @endif
                                    </svg>
                                </span>
                                <span class="text-sm text-slate-600">{{ $row['label'] }}</span>
                            </div>
                            <span class="flex items-center gap-1.5 text-sm font-semibold {{ $valueColor[$st] ?? 'text-slate-600' }}">
                                {{ $row['value'] }}
                            </span>
                        </div>
                        @empty
                        <p class="py-6 text-center text-sm text-slate-400">No detailed analysis was recorded for this scan.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Similar Trusted Domain -->
            @if($similar)
            <div>
                <h3 class="mb-2 text-sm font-semibold text-slate-700">Similar Trusted Domain</h3>
                <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                    <div>
                        <p class="font-mono text-sm font-semibold text-emerald-800">{{ $similar['sample_email'] }}</p>
                        <p class="mt-1 flex gap-6 text-xs text-emerald-700">
                            <span>Last email: <span class="font-medium">{{ $similar['last_at'] ?? '—' }}</span></span>
                            <span>Total emails: <span class="font-medium">{{ $similar['total'] }}</span></span>
                        </p>
                    </div>
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                </div>
            </div>
            @endif

            <!-- Recommendations + findings -->
            @if($scan->recommendations && count($scan->recommendations))
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Recommended action</p>
                <ul class="space-y-1.5">
                    @foreach($scan->recommendations as $rec)
                    <li class="flex items-start gap-2 text-sm text-slate-700">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        {{ $rec }}
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <a href="{{ route('email-scans.index') }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Go Back</a>
                <a href="{{ route('email-scans.index') }}" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">I Understand, Continue</a>
            </div>

        </div>
    </div>
</x-app-layout>
