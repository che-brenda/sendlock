<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Customer Billing</h2>
            <p class="text-sm text-slate-400">Subscription &amp; payment status across every organization</p>
        </div>
    </x-slot>

    @php $methods = config('sendlock.billing.payment_methods'); @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Summary -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Active subscriptions</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $stats['active'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Awaiting payment</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600">{{ $stats['pending'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total revenue</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $symbol }}{{ number_format($stats['revenue'], 2) }}</p>
                </div>
            </div>

            <!-- Customers -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Organizations</h3>
                    <p class="text-sm text-slate-400">{{ $organizations->count() }} total</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Organization</th>
                                <th class="px-6 py-3">Plan</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Subscribed</th>
                                <th class="px-6 py-3">Expires</th>
                                <th class="px-6 py-3">Last payment</th>
                                <th class="px-6 py-3 text-right">Total paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($organizations as $org)
                            @php $last = $org->payments->first(); @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-800">{{ $org->organization_name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ ucfirst($org->type) }}@if($org->parent) · under {{ $org->parent->organization_name }}@endif
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $org->planLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-subscription-badge :organization="$org" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $org->subscribed_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $org->subscription_expires_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if($last)
                                        <p class="text-slate-700">{{ $symbol }}{{ number_format((float) $last->amount, 2) }} · {{ $methods[$last->payment_method]['name'] ?? $last->payment_method }}</p>
                                        <p class="text-xs text-slate-400">{{ $last->paid_at?->format('M d, Y') }}</p>
                                    @else
                                        <span class="text-slate-300">No payments</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-semibold text-slate-800">{{ $symbol }}{{ number_format((float) $org->paid_total, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">No organizations yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
