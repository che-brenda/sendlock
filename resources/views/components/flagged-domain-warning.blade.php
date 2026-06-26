@php
    $warning = session('domain_warning');
@endphp

@if($warning)
@php
    $typeLabels = [
        'lookalike' => 'Lookalike of a trusted vendor',
        'homograph' => 'Homograph / IDN spoofing',
        'typosquat' => 'Possible typosquat',
        'subdomain_abuse' => 'Brand used as a subdomain',
        'disposable' => 'Disposable email domain',
        'suspicious_tld' => 'High-risk TLD',
        'entropy' => 'Randomly generated domain',
        'untrusted' => 'Untrusted domain',
    ];
    $email = $warning['email'] ?? [];
    $isSend = ($warning['context'] ?? 'scan') === 'send';
@endphp

<div x-data="{ open: true }"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none;">

    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>

    <!-- Dialog -->
    <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start gap-4 border-b border-slate-100 bg-amber-50 px-6 py-5">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                </svg>
            </span>
            <div>
                <h3 class="text-base font-semibold text-slate-900">Previously flagged domain</h3>
                <p class="mt-0.5 text-sm text-slate-600">
                    This domain has been flagged and used before. Proceed with caution.
                </p>
            </div>
        </div>

        <div class="space-y-3 px-6 py-5">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-sm font-semibold text-slate-800">{{ $warning['domain'] }}</span>
                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                        {{ $typeLabels[$warning['type']] ?? ucfirst($warning['type']) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ $warning['reason'] }}</p>
                @if(!empty($warning['resembles']))
                <p class="mt-1 text-xs text-slate-500">Resembles trusted domain: <span class="font-medium">{{ $warning['resembles'] }}</span></p>
                @endif
                <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-400">
                    Seen {{ $warning['times_seen'] }} {{ \Illuminate\Support\Str::plural('time', $warning['times_seen']) }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4">
            <button type="button" @click="open = false"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                {{ $isSend ? 'Cancel' : 'Dismiss' }}
            </button>

            <!-- Request manager authorization -->
            <form method="POST" action="{{ route('flagged-domains.request-approval') }}">
                @csrf
                <input type="hidden" name="recipient_email" value="{{ $email['recipient_email'] ?? '' }}">
                <input type="hidden" name="subject" value="{{ $email['subject'] ?? '' }}">
                <input type="hidden" name="email_content" value="{{ $email['email_content'] ?? '' }}">
                <button type="submit"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Request manager authorization
                </button>
            </form>

            @if($isSend)
            <!-- Override: send anyway (re-submits with acknowledgement) -->
            <form method="POST" action="{{ route('protected-email.store') }}">
                @csrf
                <input type="hidden" name="acknowledged" value="1">
                <input type="hidden" name="recipient_email" value="{{ $email['recipient_email'] ?? '' }}">
                <input type="hidden" name="subject" value="{{ $email['subject'] ?? '' }}">
                <input type="hidden" name="email_content" value="{{ $email['email_content'] ?? '' }}">
                <button type="submit"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Send anyway
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endif
