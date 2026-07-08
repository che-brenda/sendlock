<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Approvals</h2>
            <p class="text-sm text-slate-400">Sign off on sends awaiting approval</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            @forelse($requests as $req)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-800">{{ $req->recipient_email }}</p>
                        <p class="text-sm text-slate-500">{{ $req->subject ?? 'No subject' }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            Requested by {{ $req->user?->name }} ·
                            Risk {{ $req->risk_level }} ({{ $req->risk_score }}) ·
                            {{ $req->recipient_verified_at ? 'Recipient verified' : 'No recipient verification' }}
                        </p>
                    </div>
                    <x-status-badge :status="$req->status" />
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <form method="POST" action="{{ route('approvals.approve', $req) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="text" name="notes" placeholder="Note (optional)" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <x-confirm-submit label="Approve" message="Approve and clear this send?" confirm="Yes, approve" class="bg-emerald-600 text-white hover:bg-emerald-700" />
                    </form>
                    <form method="POST" action="{{ route('approvals.reject', $req) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="text" name="notes" placeholder="Reason (optional)" class="flex-1 rounded-lg border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">
                        <x-confirm-submit label="Reject" message="Reject this send?" confirm="Yes, reject" class="bg-rose-600 text-white hover:bg-rose-700" />
                    </form>
                </div>
            </div>
            @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-400 shadow-sm">
                Nothing awaiting approval.
            </div>
            @endforelse

        </div>
    </div>
</x-app-layout>
