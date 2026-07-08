<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms &amp; Conditions — {{ config('app.name', 'SendLock') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-700 antialiased">

    <!-- Header -->
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-16 max-w-4xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-teal-700">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-slate-900">SendLock</span>
            </a>
            <a href="{{ url()->previous() === url()->current() ? route('register') : url()->previous() }}"
               class="text-sm font-medium text-teal-600 hover:text-teal-700">&larr; Back</a>
        </div>
    </header>

    <!-- Content -->
    <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">

            <h1 class="text-2xl font-bold text-slate-900">Terms &amp; Conditions</h1>
            <p class="mt-2 text-sm text-slate-400">Last updated {{ now()->format('F Y') }}</p>

            <p class="mt-6 text-sm leading-relaxed text-slate-600">
                These Terms &amp; Conditions ("Terms") govern your access to and use of the SendLock
                platform ("Service"). By creating an organization or otherwise using the Service, you
                agree to be bound by these Terms on behalf of your organization.
            </p>

            @php
                $sections = [
                    ['1. Accounts &amp; Organizations',
                        'Registration creates an organization (the tenant) and its first administrator account. You are responsible for the accuracy of the information you provide, for all activity under your organization\'s accounts, and for keeping credentials confidential. Administrators may create additional users, who must set their own password on first sign-in.'],
                    ['2. Acceptable use',
                        'You agree to use the Service only for lawful business communication-security purposes. You may not attempt to circumvent tenant isolation, access another organization\'s data, probe or load-test the Service without authorization, or use it to transmit unlawful, infringing, or malicious content.'],
                    ['3. Subscriptions &amp; plans',
                        'The Service is offered under tiered plans (Free, Beta, Pro, Enterprise). Paid features — including AI content classification and SMS/WhatsApp recipient verification — are enabled only for organizations whose plan entitles them. Plan entitlements, limits, and fees may change with notice.'],
                    ['4. Email scanning &amp; risk scoring',
                        'The Service analyzes email metadata and content you submit to produce risk scores and recommendations. Risk scoring is advisory and provided on a best-effort basis; it does not guarantee detection or prevention of every fraudulent, malicious, or misdirected message. Final send/approval decisions remain your responsibility.'],
                    ['5. Data &amp; privacy',
                        'Content you submit for scanning is processed to deliver the Service and is retained as audit and scan history scoped to your organization. We apply reasonable technical and organizational measures to protect it. We do not sell your data. Third-party providers (e.g. verification or threat-intelligence vendors) are engaged only as needed to deliver features you enable.'],
                    ['6. Security',
                        'We employ role-based access control, tenant scoping, and audit logging to protect your data. You agree to promptly notify us of any suspected unauthorized access or security incident involving your organization\'s accounts.'],
                    ['7. Service availability',
                        'We aim for high availability but do not warrant uninterrupted or error-free operation. The Service may be modified, suspended for maintenance, or updated from time to time.'],
                    ['8. Limitation of liability',
                        'To the maximum extent permitted by law, the Service is provided "as is" without warranties of any kind, and SendLock shall not be liable for indirect, incidental, or consequential damages arising from your use of, or inability to use, the Service.'],
                    ['9. Termination',
                        'You may stop using the Service at any time. We may suspend or terminate access for breach of these Terms. Upon termination, your right to access the Service ceases; data handling on termination follows our then-current data-retention practices.'],
                    ['10. Changes to these Terms',
                        'We may update these Terms as the Service evolves. Material changes will be communicated through the Service or by email to your organization\'s primary contact. Continued use after changes take effect constitutes acceptance.'],
                    ['11. Contact',
                        'Questions about these Terms can be directed to your SendLock account contact or the organization administrator who set up your account.'],
                ];
            @endphp

            <div class="mt-8 space-y-7">
                @foreach($sections as [$heading, $body])
                <section>
                    <h2 class="text-base font-semibold text-slate-900">{!! $heading !!}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{!! $body !!}</p>
                </section>
                @endforeach
            </div>

            <div class="mt-10 border-t border-slate-100 pt-6">
                <a href="{{ route('register') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">&larr; Back to registration</a>
            </div>
        </div>
    </main>

</body>
</html>
