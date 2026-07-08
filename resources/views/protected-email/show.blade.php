<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Protected Send</h2>
            <p class="text-sm text-slate-400">{{ $approvalRequest->recipient_email }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
            @endif

            <a href="{{ route('protected-email.create') }}" class="inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Back to Send Protected
            </a>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">Status</p>
                        <div class="mt-1"><x-status-badge :status="$approvalRequest->status" /></div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Risk</p>
                        <p class="text-2xl font-bold text-slate-900">{{ $approvalRequest->risk_score }}<span class="text-base text-slate-400">/100</span></p>
                        <p class="text-xs text-slate-500">{{ $approvalRequest->risk_level }} · {{ $approvalRequest->decision }}</p>
                    </div>
                </div>

                <dl class="mt-6 divide-y divide-slate-100 border-t border-slate-100 text-sm">
                    <div class="flex justify-between py-3"><dt class="text-slate-500">Recipient</dt><dd class="font-medium text-slate-800">{{ $approvalRequest->recipient_email }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-500">Subject</dt><dd class="font-medium text-slate-800">{{ $approvalRequest->subject ?? '—' }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-500">Requested by</dt><dd class="font-medium text-slate-800">{{ $approvalRequest->user?->name }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-500">Recipient verified</dt><dd class="font-medium text-slate-800">{{ $approvalRequest->recipient_verified_at?->format('M d, Y H:i') ?? 'No' }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-500">Released</dt><dd class="font-medium text-slate-800">{{ $approvalRequest->released_at?->format('M d, Y H:i') ?? '—' }}</dd></div>
                </dl>
            </div>

            @php
                $field = 'block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
                $canApprove = auth()->user()->isSuperAdmin() || auth()->user()->isOrgAdmin()
                    || auth()->user()->isHeadOrgAdmin() || auth()->user()->hasRole('Manager');
            @endphp

            {{-- Untrusted recipient → present the options (nothing has been sent) --}}
            @if(! $approvalRequest->isTerminal() && ! $alreadyTrusted)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <h3 class="text-base font-semibold text-amber-900">Recipient not trusted — how do you want to proceed?</h3>
                <p class="mt-1 text-sm text-amber-800">
                    <span class="font-medium">{{ $approvalRequest->recipient_email }}</span> is not in your trusted database, so nothing has been sent.
                    Choose an option below.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($approvalRequest->status === 'PENDING_VERIFICATION')
                    <a href="{{ route('recipient-verification.index') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                        Verify recipient (SMS / WhatsApp)
                    </a>
                    <form method="POST" action="{{ route('protected-email.escalate', $approvalRequest) }}">
                        @csrf
                        <x-confirm-submit label="Request manager authorization"
                                          message="Send this to a manager for authorization?"
                                          confirm="Yes"
                                          class="bg-slate-900 text-white hover:bg-slate-700" />
                    </form>
                    @endif

                    @if($approvalRequest->email_scan_id)
                    <a href="{{ route('email-scans.show', $approvalRequest->email_scan_id) }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        View risk analysis
                    </a>
                    @endif

                    <form method="POST" action="{{ route('protected-email.cancel', $approvalRequest) }}" onsubmit="return confirm('Cancel this send? It will not be delivered.')">
                        @csrf
                        <button class="inline-flex items-center gap-2 rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Cancel</button>
                    </form>
                </div>

                @if($approvalRequest->status === 'PENDING_APPROVAL')
                <p class="mt-4 border-t border-amber-200 pt-3 text-sm text-amber-800">
                    Awaiting a manager's authorization.
                    @if($canApprove)<a href="{{ route('approvals.index') }}" class="font-semibold underline">Open the approvals queue</a>.@endif
                </p>
                @endif
            </div>
            @endif

            {{-- Cleared to send → the final human "Send" step (nothing auto-sends) --}}
            @if($approvalRequest->status === 'RELEASED' && ! $approvalRequest->sent_at)
            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-emerald-900">Safe to send</h3>
                        <p class="mt-0.5 text-sm text-emerald-700">This message passed all checks. Review below and press Send to dispatch it.</p>

                        <ul class="mt-3 space-y-1.5 text-sm text-emerald-800">
                            @if($alreadyTrusted)
                            <li class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Recipient found in your trusted database</li>
                            @endif
                            @if($approvalRequest->recipient_verified_at)
                            <li class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Recipient identity verified</li>
                            @endif
                            @if($approvalRequest->actions->where('action', 'APPROVED')->isNotEmpty())
                            <li class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Approved by a manager</li>
                            @endif
                            <li class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Risk level: {{ $approvalRequest->risk_level }} ({{ $approvalRequest->risk_score }}/100)</li>
                        </ul>

                        <form method="POST" action="{{ route('protected-email.send', $approvalRequest) }}" class="mt-4">
                            @csrf
                            <x-confirm-submit label="Send email"
                                              message="Are you sure you want to send this email to {{ $approvalRequest->recipient_email }}?"
                                              confirm="Yes, send"
                                              class="bg-emerald-600 text-white hover:bg-emerald-700" />
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- Already sent --}}
            @if($approvalRequest->sent_at)
            <div class="flex items-center gap-3 rounded-2xl border border-teal-200 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                <svg class="h-5 w-5 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.126A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.876L5.999 12Zm0 0h7.5" /></svg>
                <span><span class="font-semibold">Sent</span> to {{ $approvalRequest->recipient_email }} on {{ $approvalRequest->sent_at->format('M d, Y H:i') }}.</span>
            </div>
            @endif

            {{-- Verified AND approved → ask the user to confirm adding to the trusted database --}}
            @if($approvalRequest->wasVerifiedAndApproved() && ! $alreadyTrusted && ! $approvalRequest->sent_at)
            @php $recipientDomain = \App\Services\RiskEngine::domainFromEmail($approvalRequest->recipient_email); @endphp
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-emerald-900">Add to your trusted database?</h3>
                        <p class="mt-1 text-sm text-emerald-700">
                            <span class="font-medium">{{ $approvalRequest->recipient_email }}</span> was verified and approved.
                            You can trust it so future sends are released automatically — this is optional and needs your confirmation.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('protected-email.trust', $approvalRequest) }}"
                                  onsubmit="return confirm('Trust the address {{ $approvalRequest->recipient_email }}? Only this exact address will be trusted.')">
                                @csrf
                                <input type="hidden" name="scope" value="address">
                                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yes — trust this address</button>
                            </form>
                            <form method="POST" action="{{ route('protected-email.trust', $approvalRequest) }}"
                                  onsubmit="return confirm('Trust the ENTIRE domain {{ $recipientDomain }}? Every address on it will be treated as trusted — only do this for a domain you fully control or fully trust.')">
                                @csrf
                                <input type="hidden" name="scope" value="domain">
                                <button class="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Trust the whole domain ({{ $recipientDomain }})</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Workflow timeline -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-base font-semibold text-slate-800">Workflow</h3>
                <ol class="space-y-3 text-sm">
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-teal-600 text-xs font-bold text-white">1</span>
                        <span class="text-slate-700">Risk evaluated — {{ $approvalRequest->risk_level }} ({{ $approvalRequest->decision }})</span>
                    </li>
                    @if($approvalRequest->verifications->isNotEmpty() || $approvalRequest->recipient_verified_at)
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $approvalRequest->recipient_verified_at ? 'bg-emerald-600' : 'bg-amber-500' }} text-xs font-bold text-white">2</span>
                        <span class="text-slate-700">Recipient verification — {{ $approvalRequest->recipient_verified_at ? 'verified' : 'pending' }}</span>
                    </li>
                    @endif
                    @foreach($approvalRequest->actions as $action)
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $action->action === 'APPROVED' ? 'bg-emerald-600' : 'bg-rose-600' }} text-xs font-bold text-white">✓</span>
                        <span class="text-slate-700">{{ ucfirst(strtolower($action->action)) }} by {{ $action->user?->name }} @if($action->notes)— {{ $action->notes }}@endif</span>
                    </li>
                    @endforeach
                    @if($approvalRequest->status === 'RELEASED')
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">→</span>
                        <span class="font-medium text-emerald-700">Released for sending</span>
                    </li>
                    @endif
                </ol>
            </div>

        </div>
    </div>
</x-app-layout>
