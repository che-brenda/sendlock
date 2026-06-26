<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>SendLock — Business Communication Trust Platform</title>
        <meta name="description" content="SendLock prevents business email compromise, vendor fraud and misdirected emails by verifying recipients and approving sends before sensitive information leaves your organization.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-700">

        <x-marketing.header />

        <!-- Hero -->
        <section class="relative overflow-hidden bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-teal-900/60"></div>
            <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-teal-500/20 blur-3xl"></div>
            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-teal-400/30 bg-teal-400/10 px-3 py-1 text-xs font-semibold text-teal-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span>
                        Outbound-first email security
                    </span>
                    <h1 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
                        Stop fraud <span class="text-teal-400">before</span> the email is sent.
                    </h1>
                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-300">
                        SendLock verifies recipients and approves high-risk sends — preventing business
                        email compromise, vendor fraud and misdirected payments that traditional
                        inbound gateways miss.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="rounded-lg bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-teal-900/40 hover:bg-teal-500">Request a Demo</a>
                        <a href="#how" class="rounded-lg border border-white/20 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">See how it works</a>
                    </div>
                    <div class="mt-8 flex items-center gap-6 text-sm text-slate-400">
                        <span class="flex items-center gap-2"><svg class="h-4 w-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> Multi-tenant SaaS</span>
                        <span class="flex items-center gap-2"><svg class="h-4 w-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> Recipient verification</span>
                    </div>
                </div>

                <!-- Hero visual: a "Send Blocked" risk card -->
                <div class="relative">
                    <div class="rounded-2xl border border-white/10 bg-white p-6 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100"><svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" /></svg></span>
                                <span class="font-semibold text-slate-900">Send Blocked</span>
                            </div>
                            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700">RISK 85/100</span>
                        </div>
                        <p class="mt-4 text-sm text-slate-500">Recipient</p>
                        <p class="font-medium text-rose-600">claims@activa-assur.com</p>
                        <p class="mt-1 text-sm text-slate-500">Did you mean <span class="font-medium text-emerald-600">claims@activa-assurances.com</span>?</p>

                        <div class="mt-4 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
                            @foreach(['Similarity to a trusted domain', 'Domain registered 12 days ago', 'New bank account detected', 'No prior communication'] as $reason)
                            <p class="flex items-center gap-2 text-slate-600"><svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg> {{ $reason }}</p>
                            @endforeach
                        </div>
                        <div class="mt-4 flex gap-2">
                            <span class="flex-1 rounded-lg bg-teal-600 px-4 py-2 text-center text-sm font-semibold text-white">Verify Recipient</span>
                            <span class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-600">Request Approval</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trust bar -->
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <p class="text-center text-xs font-semibold uppercase tracking-widest text-slate-400">Built for industries that move money over email</p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-sm font-semibold text-slate-400">
                    @foreach(['Banking', 'Insurance', 'Logistics', 'Law Firms', 'Procurement', 'Enterprise'] as $industry)
                    <span>{{ $industry }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Compare: outbound vs inbound -->
        <section id="compare" class="bg-slate-50 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">The gap traditional gateways leave open</h2>
                    <p class="mt-4 text-slate-600">SendLock protects them by controlling what leaves the system.</p>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">Provides Both Inbound and Outbound Protection</h2>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-8">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Traditional email security</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900">Inbound detection</h3>
                        <ul class="mt-5 space-y-3 text-sm text-slate-600">
                            @foreach(['Stops malicious incoming email', 'Filters spam and malware', 'Blocks known-bad domains', 'Users can still send to the wrong place'] as $i => $item)
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $i === 3 ? 'text-rose-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $i === 3 ? 'M6 18 18 6M6 6l12 12' : 'm4.5 12.75 6 6 9-13.5' }}" /></svg>
                                {{ $item }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-2xl border-2 border-teal-500 bg-white p-8 shadow-lg shadow-teal-100">
                        <p class="text-xs font-semibold uppercase tracking-wider text-teal-600">SendLock</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900">Outbound protection</h3>
                        <ul class="mt-5 space-y-3 text-sm text-slate-600">
                            @foreach(['Verifies the recipient before sending', 'Detects vendor & bank-change fraud', 'Requires approval for high-risk sends', 'Prevents misdirected sensitive data'] as $item)
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-teal-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                {{ $item }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="bg-white py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">One platform, every signal</h2>
                    <p class="mt-4 text-slate-600">A layered risk engine scores every message across domain, content, financial and authentication signals.</p>
                </div>
                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $features = [
                            ['Domain Intelligence', 'Lookalike, typosquat and trust-list analysis catches impersonation domains.', 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3'],
                            ['Content & Financial AI', 'Detects fraud-intent language and bank-detail changes that differ from a vendor\'s known account.', 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33'],
                            ['Recipient Verification', 'Confirm recipients via SMS, WhatsApp or email before sensitive information is released.', 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z'],
                            ['Approval Workflows', 'Route medium and high-risk sends through manager and security sign-off.', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            ['Trust Center', 'Centralize trusted vendors, blocked domains, verified recipients and known bank accounts.', 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18'],
                            ['Threat Intelligence', 'Score domains against a platform-wide malicious-domain feed plus SPF/DKIM/DMARC posture.', 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                        ];
                    @endphp
                    @foreach($features as [$title, $desc, $icon])
                    <div class="group rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-teal-300 hover:shadow-lg">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-600 transition group-hover:bg-teal-600 group-hover:text-white">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                        </span>
                        <h3 class="mt-4 font-semibold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $desc }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Verification differentiator -->
        <section id="verification" class="bg-slate-950 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-teal-400">The SendLock difference</span>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight text-white">Verify the human, not just the header</h2>
                        <p class="mt-4 text-slate-300">When a send looks risky, SendLock pauses it and confirms the recipient out-of-band — the layer no inbound gateway provides.</p>
                        <div class="mt-8 space-y-4">
                            @foreach([['Detect risk', 'The engine scores domain, content, financial and auth signals.'], ['Verify recipient', 'A code is sent via SMS, WhatsApp or email and confirmed.'], ['Approve & release', 'Manager and security sign-off before the email goes out.']] as $i => [$t, $d])
                            <div class="flex gap-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-500/20 text-sm font-bold text-teal-300">{{ $i + 1 }}</span>
                                <div>
                                    <p class="font-semibold text-white">{{ $t }}</p>
                                    <p class="text-sm text-slate-400">{{ $d }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-8 backdrop-blur">
                        <div class="space-y-4">
                            <div class="rounded-xl bg-white p-4 shadow-lg">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Verification sent</p>
                                <p class="mt-1 text-sm font-medium text-slate-800">WhatsApp code to +1 ••• ••• 4821</p>
                            </div>
                            <div class="flex justify-center gap-2">
                                @foreach(['4','8','2','1','0','7'] as $n)<span class="flex h-11 w-9 items-center justify-center rounded-lg bg-white text-lg font-bold text-slate-900 shadow">{{ $n }}</span>@endforeach
                            </div>
                            <div class="rounded-xl bg-teal-500/20 p-4 text-center text-sm font-semibold text-teal-200">Recipient verified · awaiting approval</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how" class="bg-white py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">The protection pipeline</h2>
                    <p class="mt-4 text-slate-600">Every outbound message flows through the decision engine.</p>
                </div>
                <div class="mt-14 grid gap-6 md:grid-cols-4">
                    @foreach([['Analyze', 'Domain, content, financial, attachment & auth signals'], ['Decide', 'Allow · Approve · Verify · Quarantine'], ['Verify', 'Out-of-band recipient confirmation'], ['Release', 'Approved sends leave with a full audit trail']] as $i => [$t, $d])
                    <div class="relative rounded-2xl border border-slate-200 bg-slate-50 p-6">
                        <span class="text-4xl font-extrabold text-teal-200">0{{ $i + 1 }}</span>
                        <h3 class="mt-2 font-semibold text-slate-900">{{ $t }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $d }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Solutions -->
        <section id="solutions" class="bg-slate-50 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">Built to stop real losses</h2>
                    <p class="mt-4 text-slate-600">The fraud types that cost organizations the most.</p>
                </div>
                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach(['Business Email Compromise', 'Invoice & payment fraud', 'CEO impersonation', 'Vendor account takeover', 'Misdirected email', 'Logistics & cargo fraud'] as $sol)
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg></span>
                        <span class="font-medium text-slate-800">{{ $sol }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="bg-white py-16">
            <div class="mx-auto grid max-w-5xl gap-8 px-4 text-center sm:grid-cols-3 sm:px-6 lg:px-8">
                @foreach([['8', 'risk signals per scan'], ['4', 'decision outcomes'], ['100%', 'sends audit-logged']] as [$stat, $label])
                <div>
                    <p class="text-4xl font-extrabold text-teal-600">{{ $stat }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $label }}</p>
                </div>
                @endforeach
            </div>
        </section>

        <!-- CTA band -->
        <section class="bg-gradient-to-br from-teal-700 to-teal-900 py-16">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-6 px-4 text-center sm:px-6 lg:flex-row lg:text-left lg:px-8">
                <div>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl">Protect your outbound communications today.</h2>
                    <p class="mt-2 text-teal-100">Set up your organization in minutes — no inbound migration required.</p>
                </div>
                <div class="flex shrink-0 gap-3">
                    <a href="{{ route('register') }}" class="rounded-lg bg-white px-6 py-3 text-sm font-semibold text-teal-700 shadow hover:bg-teal-50">Get started</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-white/40 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">Log in</a>
                </div>
            </div>
        </section>

        <x-marketing.footer />
    </body>
</html>
