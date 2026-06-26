<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Sub-Organizations</h2>
            <p class="text-sm text-slate-400">Managed under {{ $head->organization_name }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ $subOrganizations->count() }} sub-organization(s)</p>
                <a href="{{ route('sub-organizations.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Sub-Organization
                </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Organization</th>
                                <th class="px-6 py-3">Industry</th>
                                <th class="px-6 py-3">Users</th>
                                <th class="px-6 py-3">Departments</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($subOrganizations as $sub)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $sub->organization_name }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $sub->industry ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $sub->users_count }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $sub->departments_count }}</td>
                                <td class="px-6 py-3">
                                    @if($sub->status)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Active</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('sub-organizations.show', $sub) }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-slate-400">No sub-organizations yet. Add one to get started.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
