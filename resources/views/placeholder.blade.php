<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ $title }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-100">
                    <svg class="h-7 w-7 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>

                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ $title }}</h3>

                <span class="mt-3 inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                    Planned · {{ $phase }}
                </span>

                <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-slate-500">
                    {{ $summary }}
                </p>

                <a href="{{ route('dashboard') }}"
                   class="mt-6 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
