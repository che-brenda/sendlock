@props(['status'])

@php
$styles = [
    'PENDING_VERIFICATION' => 'bg-amber-100 text-amber-700 border-amber-200',
    'PENDING_APPROVAL' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'RELEASED' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border-rose-200',
    'BLOCKED' => 'bg-rose-100 text-rose-700 border-rose-200',
];
$labels = [
    'PENDING_VERIFICATION' => 'Awaiting Verification',
    'PENDING_APPROVAL' => 'Awaiting Approval',
    'RELEASED' => 'Released',
    'REJECTED' => 'Rejected',
    'BLOCKED' => 'Blocked',
];
@endphp

<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $styles[$status] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
    {{ $labels[$status] ?? $status }}
</span>
