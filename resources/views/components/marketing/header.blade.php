@php
    $platform = [
        ['Domain Intelligence', 'Lookalike & typosquat detection', '#features'],
        ['Content & Financial AI', 'Intent and bank-change detection', '#features'],
        ['Recipient Verification', 'SMS / WhatsApp / email confirmation', '#verification'],
        ['Approval Workflows', 'Manager & security sign-off', '#verification'],
        ['Trust Center', 'Vendors, domains & verified recipients', '#features'],
        ['Threat Intelligence', 'Global malicious-domain feed', '#features'],
    ];
    $solutions = [
        ['Business Email Compromise', 'Stop CEO & vendor impersonation', '#solutions'],
        ['Invoice & Payment Fraud', 'Catch bank-detail changes', '#solutions'],
        ['Misdirected Email', 'Prevent wrong-recipient sends', '#solutions'],
        ['Logistics & Insurance', 'Cargo and claims fraud', '#solutions'],
    ];
    $resources = [
        ['How it works', 'The protection pipeline', '#how'],
        ['Why SendLock', 'Outbound vs inbound security', '#compare'],
        ['Documentation', 'Product guides', '#'],
    ];
@endphp

<header x-data="{ mobile: false, open: null }" class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">

        <!-- Brand -->
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-teal-700">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </span>
            <span class="text-lg font-bold tracking-tight text-slate-900">SendLock</span>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden items-center gap-1 lg:flex" @mouseleave="open = null">
            @foreach(['Platform' => $platform, 'Solutions' => $solutions, 'Resources' => $resources] as $label => $items)
            <div class="relative" @mouseenter="open = '{{ $label }}'">
                <button class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                        :class="open === '{{ $label }}' && 'text-slate-900'">
                    {{ $label }}
                    <svg class="h-3.5 w-3.5 transition" :class="open === '{{ $label }}' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div x-show="open === '{{ $label }}'" x-cloak x-transition.opacity
                     class="absolute left-0 top-full w-80 pt-2">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                        @foreach($items as [$title, $desc, $href])
                        <a href="{{ $href }}" class="block rounded-xl px-3 py-2.5 hover:bg-slate-50">
                            <span class="block text-sm font-semibold text-slate-800">{{ $title }}</span>
                            <span class="block text-xs text-slate-500">{{ $desc }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
            <a href="#compare" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Company</a>
        </nav>

        <!-- Desktop CTAs -->
        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <a href="{{ url('/dashboard') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:text-slate-900">Log in</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">Request a Demo</a>
            @endauth
        </div>

        <!-- Mobile toggle -->
        <button @click="mobile = !mobile" class="text-slate-600 lg:hidden">
            <svg x-show="!mobile" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            <svg x-show="mobile" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobile" x-cloak x-transition class="border-t border-slate-200 bg-white lg:hidden">
        <div class="space-y-1 px-4 py-4">
            <a href="#features" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Platform</a>
            <a href="#solutions" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Solutions</a>
            <a href="#how" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">How it works</a>
            <a href="#compare" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Why SendLock</a>
            <div class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-center text-sm font-semibold text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-center text-sm font-semibold text-white">Request a Demo</a>
                @endauth
            </div>
        </div>
    </div>
</header>
