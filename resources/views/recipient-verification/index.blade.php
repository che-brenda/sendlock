<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Recipient Verification Center</h2>
            <p class="text-sm text-slate-400">Confirm recipient identity before high-risk emails proceed</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            @forelse($requests as $req)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-800">{{ $req->recipient_email }}</p>
                        <p class="text-sm text-slate-500">{{ $req->subject ?? 'No subject' }} · {{ $req->risk_level }} ({{ $req->risk_score }})</p>
                    </div>
                    <x-status-badge :status="$req->status" />
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Send code -->
                    <form method="POST" action="{{ route('recipient-verification.send', $req) }}" class="space-y-3 rounded-xl bg-slate-50 p-4">
                        @csrf
                        <p class="text-sm font-medium text-slate-700">1. Send verification code</p>
                        <select name="channel" class="block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                        </select>
                        <input type="text" name="phone" placeholder="Phone (for SMS/WhatsApp)" class="block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <button class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Send Code</button>
                    </form>

                    <!-- Verify code -->
                    <form method="POST" action="{{ route('recipient-verification.verify', $req) }}" class="space-y-3 rounded-xl bg-slate-50 p-4">
                        @csrf
                        <p class="text-sm font-medium text-slate-700">2. Confirm code</p>
                        <input type="text" name="code" placeholder="6-digit code" required class="block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        @php $pending = $req->verifications->firstWhere('status', 'PENDING'); @endphp
                        <p class="text-xs text-slate-400">
                            {{ $pending ? 'Code sent via ' . $pending->channel . ' (check application log in stub mode).' : 'No active code — send one first.' }}
                        </p>
                        <button class="w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Verify</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-400 shadow-sm">
                No requests awaiting recipient verification.
            </div>
            @endforelse

        </div>
    </div>
</x-app-layout>
