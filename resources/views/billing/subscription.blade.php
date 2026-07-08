<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Subscription</h2>
            <p class="text-sm text-slate-400">Your plan, billing dates and payment history</p>
        </div>
    </x-slot>

    @php
        $methods = config('sendlock.billing.payment_methods');
        $state = $organization->subscriptionState();
        $expires = $organization->subscription_expires_at;
        $daysLeft = $organization->daysUntilExpiry();
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Renewal alert -->
            @if($state === 'expired')
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4">
                <div class="flex items-start gap-3 text-sm text-rose-800">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    <p>Your <span class="font-semibold">{{ $organization->planLabel() }}</span> subscription expired on {{ $expires?->format('M d, Y') }}. Renew to keep its paid features.</p>
                </div>
                <a href="{{ route('billing.index') }}" class="shrink-0 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Renew now</a>
            </div>
            @elseif($state === 'expiring_soon')
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <div class="flex items-start gap-3 text-sm text-amber-800">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.008v.008H12v-.008Z" /></svg>
                    <p>Your subscription renews in <span class="font-semibold">{{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }}</span> ({{ $expires?->format('M d, Y') }}).</p>
                </div>
                <a href="{{ route('billing.index') }}" class="shrink-0 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Renew early</a>
            </div>
            @endif

            <!-- Current plan -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-6">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-2xl font-bold text-slate-900">{{ $organization->planLabel() }}</h3>
                            <x-subscription-badge :organization="$organization" />
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $package['tagline'] ?? 'Core outbound protection.' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-extrabold tracking-tight text-slate-900">
                            {{ $package ? $symbol.$package['price'] : 'Free' }}<span class="text-sm font-medium text-slate-400">{{ $package ? '/'.$package['period'] : '' }}</span>
                        </p>
                        <a href="{{ route('billing.index') }}" class="mt-2 inline-flex rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700">Change plan</a>
                    </div>
                </div>

                <!-- Key dates -->
                <dl class="grid grid-cols-1 divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div class="p-6">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Subscribed on</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $organization->subscribed_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                    <div class="p-6">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Renews / expires</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ $expires?->format('M d, Y') ?? 'No expiry (Free plan)' }}
                        </dd>
                    </div>
                    <div class="p-6">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Billing</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            @if($state === 'expired')
                                <span class="text-rose-600">Expired</span>
                            @elseif($expires)
                                Monthly · {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left
                            @else
                                No recurring charge
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- What's included -->
            @if($package)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">What's included</h3>
                <ul class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    @foreach($package['features'] as $feature)
                    <li class="flex items-start gap-2.5 text-slate-600">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        <span>{{ $feature }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Payment history -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Payment history</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Receipt</th>
                                <th class="px-6 py-3">Plan</th>
                                <th class="px-6 py-3">Method</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($payments as $payment)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-slate-700">{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-xs text-slate-500">{{ $payment->reference }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ \Illuminate\Support\Str::headline($payment->package) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $methods[$payment->payment_method]['name'] ?? $payment->payment_method }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-slate-800">{{ $symbol }}{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ ucfirst($payment->status) }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400">No payments yet — you're on the Free plan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
