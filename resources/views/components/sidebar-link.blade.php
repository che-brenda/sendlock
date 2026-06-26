@props(['active' => false, 'icon' => ''])

@php
$base = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition';
$state = $active
    ? 'bg-teal-600 text-white shadow-sm'
    : 'text-slate-300 hover:bg-slate-800 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $base . ' ' . $state]) }}>
    <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"
         fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
        {!! $icon !!}
    </svg>
    <span>{{ $slot }}</span>
</a>
