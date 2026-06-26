<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Global Threat Intelligence</h2>
            <p class="text-sm text-slate-400">Platform-wide malicious domain list — applied across all tenants</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-base font-semibold text-slate-800">Add Threat Domain</h3>
                <form method="POST" action="{{ route('threat-intel.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    @csrf
                    <input type="text" name="domain" placeholder="malicious.com" required class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                    <select name="category" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Category…</option>
                        <option value="phishing">Phishing</option>
                        <option value="malware">Malware</option>
                        <option value="bec">BEC</option>
                        <option value="spam">Spam</option>
                    </select>
                    <select name="severity" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="HIGH">High</option>
                        <option value="MEDIUM" selected>Medium</option>
                        <option value="LOW">Low</option>
                    </select>
                    <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">Add Threat</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Domain</th>
                                <th class="px-6 py-3">Category</th>
                                <th class="px-6 py-3">Severity</th>
                                <th class="px-6 py-3">Notes</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($domains as $d)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $d->domain }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $d->category ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        {{ $d->severity === 'HIGH' ? 'bg-rose-100 text-rose-700' : ($d->severity === 'LOW' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $d->severity }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-slate-600">{{ $d->notes ?? '—' }}</td>
                                <td class="px-6 py-3 text-right">
                                    <form method="POST" action="{{ route('threat-intel.destroy', $d) }}" onsubmit="return confirm('Remove this domain?');">
                                        @csrf @method('DELETE')
                                        <button class="text-sm font-medium text-rose-600 hover:text-rose-700">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No threat domains yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
