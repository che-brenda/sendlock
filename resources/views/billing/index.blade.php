<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Choose your plan</h2>
            <p class="text-sm text-slate-400">
                @if($organization?->subscriptionPending())
                    Activate {{ $organization->organization_name }} by selecting a package below.
                @else
                    Manage your SendLock subscription.
                @endif
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if($organization?->subscriptionPending())
            <div class="mx-auto mb-8 flex max-w-2xl items-start gap-3 rounded-xl border border-teal-100 bg-teal-50 px-4 py-3 text-sm text-teal-800">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p>Your organization is set up. Pick a package and complete payment to unlock your dashboard. You can change or cancel anytime.</p>
            </div>
            @endif

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                @foreach($packages as $key => $pkg)
                @php $isCurrent = ! $organization?->subscriptionPending() && strtolower($organization?->subscription_plan ?? '') === $pkg['plan']; @endphp
                <div @class([
                    'relative flex flex-col rounded-2xl border bg-white p-7 shadow-sm transition',
                    'border-teal-300 ring-2 ring-teal-500 xl:-mt-3 xl:mb-3' => $pkg['highlighted'],
                    'border-slate-200' => ! $pkg['highlighted'],
                ])>
                    @if($pkg['highlighted'])
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-teal-600 px-3 py-1 text-xs font-semibold text-white shadow">Most popular</span>
                    @endif

                    <h3 class="text-lg font-bold text-slate-900">{{ $pkg['name'] }}</h3>
                    <p class="mt-1 min-h-[2.5rem] text-sm text-slate-500">{{ $pkg['tagline'] }}</p>

                    <div class="mt-5 flex items-baseline gap-1">
                        @if($pkg['price'] == 0)
                            <span class="text-4xl font-extrabold tracking-tight text-slate-900">Free</span>
                        @else
                            <span class="text-4xl font-extrabold tracking-tight text-slate-900">{{ $symbol }}{{ $pkg['price'] }}</span>
                            <span class="text-sm font-medium text-slate-400">/{{ $pkg['period'] }}</span>
                        @endif
                    </div>

                    <ul class="mt-6 space-y-3 text-sm">
                        @foreach($pkg['features'] as $feature)
                        <li class="flex items-start gap-2.5 text-slate-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            <span>{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>

                    <div class="mt-7 pt-2">
                        @if($isCurrent)
                        <span class="flex w-full items-center justify-center rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-500">Current plan</span>
                        @elseif($pkg['price'] == 0)
                        <form method="POST" action="{{ route('billing.free') }}">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                Continue free
                            </button>
                        </form>
                        @else
                        <a href="{{ route('billing.checkout', $key) }}"
                           @class([
                               'flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition',
                               'bg-teal-600 text-white hover:bg-teal-700' => $pkg['highlighted'],
                               'bg-slate-900 text-white hover:bg-slate-700' => ! $pkg['highlighted'],
                           ])>
                            Choose {{ $pkg['name'] }}
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <p class="mt-8 text-center text-xs text-slate-400">
                Prices in {{ config('sendlock.billing.currency') }}, billed monthly. Secure checkout · Cancel anytime.
            </p>
        </div>
    </div>
</x-app-layout>
