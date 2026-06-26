@php
    $columns = [
        'Platform' => ['Domain Intelligence', 'Content & Financial AI', 'Recipient Verification', 'Approval Workflows', 'Trust Center', 'Threat Intelligence'],
        'Solutions' => ['Business Email Compromise', 'Invoice & Payment Fraud', 'Misdirected Email', 'Vendor Impersonation', 'Logistics & Insurance'],
        'Company' => ['About', 'Careers', 'Partners', 'Contact', 'Security'],
        'Resources' => ['How it works', 'Documentation', 'Why SendLock', 'Trust & Compliance', 'Status'],
    ];
@endphp

<footer class="bg-slate-950 text-slate-300">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-6">

            <!-- Brand blurb -->
            <div class="col-span-2">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-teal-700">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                    </span>
                    <span class="text-lg font-bold tracking-tight text-white">SendLock</span>
                </a>
                <p class="mt-4 max-w-xs text-sm leading-6 text-slate-400">
                    The business communication trust platform. Stop sensitive information from
                    reaching the wrong recipient or a fraudulent actor — before the email is sent.
                </p>
                <div class="mt-5 flex gap-3">
                    @foreach(['M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8v-6.93H7.9V12H10V9.8c0-2.07 1.23-3.22 3.12-3.22.9 0 1.85.16 1.85.16v2.03h-1.04c-1.03 0-1.35.64-1.35 1.3V12h2.3l-.37 2.87h-1.93v6.93c4.56-.93 8-4.96 8-9.8z', 'M21.54 7.2s-.2-1.4-.8-2.02c-.77-.8-1.63-.8-2.02-.85C15.9 4.1 12 4.1 12 4.1h-.01s-3.9 0-6.72.23c-.4.05-1.25.05-2.02.85-.6.62-.8 2.02-.8 2.02S2.25 8.85 2.25 10.5v1.54c0 1.65.2 3.3.2 3.3s.2 1.4.8 2.02c.77.8 1.78.78 2.23.86 1.62.16 6.52.21 6.52.21s3.9-.01 6.72-.24c.4-.05 1.25-.05 2.02-.85.6-.62.8-2.02.8-2.02s.2-1.65.2-3.3V10.5c0-1.65-.2-3.3-.2-3.3zM9.93 13.9V8.96l5.02 2.48-5.02 2.46z', 'M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14zM8.34 17.5V10.2H6v7.3h2.34zM7.17 9.16a1.36 1.36 0 100-2.72 1.36 1.36 0 000 2.72zM18 17.5v-4.02c0-2.15-1.15-3.15-2.68-3.15-1.24 0-1.79.68-2.1 1.16V10.2h-2.33c.03.66 0 7.3 0 7.3h2.33v-4.08c0-.21.02-.42.08-.57.16-.42.55-.85 1.2-.85.85 0 1.18.64 1.18 1.58v3.92H18z'] as $path)
                    <a href="#" class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800 text-slate-300 hover:bg-teal-600 hover:text-white">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $path }}" /></svg>
                    </a>
                    @endforeach
                </div>
            </div>

            @foreach($columns as $heading => $links)
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $heading }}</h4>
                <ul class="mt-4 space-y-3">
                    @foreach($links as $link)
                    <li><a href="#" class="text-sm text-slate-400 transition hover:text-white">{{ $link }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-slate-800 pt-8 sm:flex-row">
            <p class="text-xs text-slate-500">&copy; {{ date('Y') }} SendLock Security. All rights reserved.</p>
            <div class="flex gap-6 text-xs text-slate-500">
                <a href="#" class="hover:text-slate-300">Privacy</a>
                <a href="#" class="hover:text-slate-300">Terms</a>
                <a href="#" class="hover:text-slate-300">Cookies</a>
                <a href="#" class="hover:text-slate-300">Trust Center</a>
            </div>
        </div>
    </div>
</footer>
