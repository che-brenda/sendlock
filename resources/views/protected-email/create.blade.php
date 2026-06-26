<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Send Protected Email</h2>
            <p class="text-sm text-slate-400">Risk-check, verify the recipient, and route for approval before sending</p>
        </div>
    </x-slot>

    <x-flagged-domain-warning />

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('protected-email.store') }}" class="space-y-4">
                    @csrf

                    @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Recipient Email</label>
                        <input type="email" name="recipient_email" value="{{ old('recipient_email') }}" required
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Subject</label>
                        <input type="text" name="subject" value="{{ old('subject') }}"
                               class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Email Content</label>
                        <textarea name="email_content" rows="6"
                                  class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('email_content') }}</textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700">Submit for Protection</button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">My Recent Requests</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Recipient</th>
                                <th class="px-6 py-3">Risk</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($requests as $req)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $req->created_at->format('M d, H:i') }}</td>
                                <td class="px-6 py-3 text-slate-700">{{ $req->recipient_email }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $req->risk_level }} ({{ $req->risk_score }})</td>
                                <td class="px-6 py-3"><x-status-badge :status="$req->status" /></td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('protected-email.show', $req) }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No requests yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
