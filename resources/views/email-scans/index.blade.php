<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Email Security Scan</h2>
            <p class="text-sm text-slate-400">Analyze a message before it is sent</p>
        </div>
    </x-slot>

    @php
        $levelStyles = [
            'SAFE' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'LOW' => 'bg-lime-100 text-lime-700 border-lime-200',
            'MEDIUM' => 'bg-amber-100 text-amber-700 border-amber-200',
            'HIGH' => 'bg-orange-100 text-orange-700 border-orange-200',
            'CRITICAL' => 'bg-rose-100 text-rose-700 border-rose-200',
        ];
        $decisionLabels = [
            'ALLOW' => 'Allow — send automatically',
            'MANAGER_APPROVAL' => 'Requires manager approval',
            'RECIPIENT_VERIFY' => 'Requires recipient verification + approval',
            'QUARANTINE' => 'Blocked — quarantined',
        ];
    @endphp

    <x-flagged-domain-warning />

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if(session('risk_level'))
                @php $lvl = session('risk_level'); @endphp
                <div class="rounded-2xl border bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-semibold {{ $levelStyles[$lvl] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                {{ $lvl }}
                            </span>
                            <span class="text-sm font-medium text-slate-600">{{ $decisionLabels[session('decision')] ?? session('decision') }}</span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-wide text-slate-400">Risk Score</p>
                            <p class="text-2xl font-bold text-slate-900">{{ session('risk_score') }}<span class="text-base text-slate-400">/100</span></p>
                            @if(session('confidence') !== null)
                            <p class="text-xs text-slate-400">{{ session('confidence') }}% confidence</p>
                            @endif
                        </div>
                    </div>

                    @if(session('recommendations') && count(session('recommendations')))
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Recommended action</p>
                        <ul class="space-y-1.5">
                            @foreach(session('recommendations') as $rec)
                            <li class="flex items-start gap-2 text-sm text-slate-700">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                {{ $rec }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if(session('findings') && count(session('findings')))
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Findings</p>
                        <ul class="space-y-1.5">
                            @foreach(session('findings') as $finding)
                            <li class="flex items-start gap-2 text-sm text-slate-600">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                {{ $finding }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            @endif

            <!-- Scan form -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('email-scans.analyze') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Sender / Counterparty Email</label>
                        <input type="email" name="sender_email" value="{{ old('sender_email') }}" required
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

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Attachments <span class="font-normal text-slate-400">(one filename per line)</span></label>
                        <textarea name="attachments" rows="2" placeholder="invoice.pdf&#10;statement.xlsm"
                                  class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('attachments') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Attachment file <span class="font-normal text-slate-400">(optional — an image/scan is read via OCR and analysed)</span></label>
                        <input type="file" name="attachment_file"
                               class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </div>

                    <!-- Message headers (optional) — BEC display-name / Reply-To / Return-Path checks -->
                    <details class="rounded-lg border border-slate-200 bg-slate-50/60 px-4 py-3" @if($errors->has('from_name') || $errors->has('reply_to') || $errors->has('return_path')) open @endif>
                        <summary class="cursor-pointer select-none text-sm font-medium text-slate-700">Message headers <span class="font-normal text-slate-400">(optional — detects spoofing)</span></summary>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">From display name</label>
                                <input type="text" name="from_name" value="{{ old('from_name') }}" placeholder="CEO Jane Doe"
                                       class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Reply-To</label>
                                <input type="text" name="reply_to" value="{{ old('reply_to') }}" placeholder="reply@example.com"
                                       class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Return-Path</label>
                                <input type="text" name="return_path" value="{{ old('return_path') }}" placeholder="bounce@example.com"
                                       class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                        </div>
                    </details>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700">Analyze Email</button>
                    </div>
                </form>
            </div>

            <!-- Recent scans -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent Scans</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Sender</th>
                                <th class="px-6 py-3">Score</th>
                                <th class="px-6 py-3">Level</th>
                                <th class="px-6 py-3">Decision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentScans as $scan)
                            <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('email-scans.show', $scan) }}'">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $scan->created_at->format('M d, H:i') }}</td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('email-scans.show', $scan) }}" class="font-medium text-teal-600 hover:text-teal-700">{{ $scan->sender_email }}</a>
                                </td>
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $scan->risk_score }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $levelStyles[$scan->risk_level] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">{{ $scan->risk_level }}</span>
                                </td>
                                <td class="px-6 py-3 text-slate-600">{{ $scan->decision }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No scans yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
