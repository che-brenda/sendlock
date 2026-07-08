<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Security Center</h2>
            <p class="text-sm text-slate-400">How SendLock protects your organization and this platform</p>
        </div>
    </x-slot>

    @php
        // Active protections, grouped by the layer they defend.
        $layers = [
            'Network & transport' => [
                ['TLS / HTTPS encryption', 'All traffic is encrypted in transit; HSTS is enforced over HTTPS.'],
                ['HTTP security headers', 'CSP, anti-clickjacking (X-Frame-Options), nosniff, Referrer-Policy and Permissions-Policy on every response.'],
            ],
            'Application firewall (WAF)' => [
                ['Attack-signature filtering', 'Blocks path traversal, SQL injection, XSS, LFI/RFI and code-execution attempts at the edge.'],
                ['Automated-scanner blocking', 'Known attack tools (sqlmap, nikto, nmap…) are rejected on sight.'],
                ['Blocked-attempt logging', 'Every blocked request is recorded for investigation.'],
            ],
            'Identity & access' => [
                ['Authentication', 'Password sign-in with brute-force rate limiting and forced first-sign-in reset for issued accounts.'],
                ['Role-based access control', 'Seven roles with least-privilege permissions enforced by route middleware.'],
                ['Out-of-band recipient verification', 'High-risk sends are confirmed via SMS, WhatsApp or email before release.'],
                ['CSRF protection', 'Every state-changing form is CSRF-token protected.'],
            ],
            'Data protection' => [
                ['Tenant isolation', "Each organization's data is scoped by organization on every query — no cross-tenant access."],
                ['Encryption at rest', 'Sensitive credentials (e.g. temporary passwords) are stored encrypted.'],
                ['Immutable audit trail', 'Every mutating action is logged with actor, IP and timestamp, visibility-scoped by role.'],
            ],
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Assurance banner -->
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 p-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500 text-white shadow">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                    </span>
                    <div>
                        <p class="text-lg font-bold text-emerald-900">Your workspace is protected</p>
                        <p class="text-sm text-emerald-700">Multiple independent security layers are active on every request.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold {{ $firewallOn ? 'bg-emerald-600 text-white' : 'bg-amber-100 text-amber-700' }}">
                    <span class="h-2 w-2 rounded-full {{ $firewallOn ? 'bg-emerald-200' : 'bg-amber-500' }}"></span>
                    Firewall {{ $firewallOn ? 'Active' : 'Disabled' }}
                </span>
            </div>

            <!-- Firewall stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Attacks blocked (30 days)</p>
                    <p class="mt-2 text-3xl font-bold text-rose-600">{{ number_format($blocked30) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Attacks blocked (all time)</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($blockedTotal) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Security layers active</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600">{{ collect($layers)->flatten(1)->count() }}</p>
                </div>
            </div>

            <!-- Posture by layer -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @foreach($layers as $layer => $items)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $layer }}</h3>
                    <ul class="mt-4 space-y-4">
                        @foreach($items as [$name, $desc])
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $name }}</p>
                                <p class="text-sm text-slate-500">{{ $desc }}</p>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>

            <!-- Recent blocked attempts (Super Admin only) -->
            @if($recent->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent blocked requests</h3>
                    <p class="text-sm text-slate-400">Platform-wide firewall activity (Super Admin)</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">When</th>
                                <th class="px-6 py-3">Rule</th>
                                <th class="px-6 py-3">IP</th>
                                <th class="px-6 py-3">Method</th>
                                <th class="px-6 py-3">Path</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recent as $event)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $event->created_at?->format('M d, H:i') }}</td>
                                <td class="whitespace-nowrap px-6 py-3"><span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">{{ \Illuminate\Support\Str::headline($event->rule) }}</span></td>
                                <td class="whitespace-nowrap px-6 py-3 font-mono text-xs text-slate-500">{{ $event->ip_address ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-slate-600">{{ $event->method }}</td>
                                <td class="px-6 py-3"><span class="line-clamp-1 font-mono text-xs text-slate-500">{{ $event->path }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
