@props(['organization'])

@php
    $state = $organization?->subscriptionState() ?? 'none';

    $meta = [
        'active'        => ['label' => 'Active',           'dot' => 'bg-emerald-500', 'cls' => 'bg-emerald-100 text-emerald-700'],
        'free'          => ['label' => 'Free plan',        'dot' => 'bg-slate-400',   'cls' => 'bg-slate-100 text-slate-600'],
        'expiring_soon' => ['label' => 'Expiring soon',    'dot' => 'bg-amber-500',   'cls' => 'bg-amber-100 text-amber-700'],
        'expired'       => ['label' => 'Expired',          'dot' => 'bg-rose-500',    'cls' => 'bg-rose-100 text-rose-700'],
        'pending'       => ['label' => 'Awaiting payment', 'dot' => 'bg-amber-500',   'cls' => 'bg-amber-100 text-amber-700'],
        'none'          => ['label' => 'No subscription',  'dot' => 'bg-slate-300',   'cls' => 'bg-slate-100 text-slate-500'],
    ][$state];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {$meta['cls']}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}
</span>
