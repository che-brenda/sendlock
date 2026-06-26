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
