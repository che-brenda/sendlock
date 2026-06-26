<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Departments</h2>
            <p class="text-sm text-slate-400">Organize your people into teams</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">{{ $departments->count() }} department(s)</p>
                <a href="{{ route('departments.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add Department
                </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Department</th>
                                <th class="px-6 py-3">Description</th>
                                <th class="px-6 py-3">Members</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Created</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($departments as $department)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-medium text-slate-800">{{ $department->department_name }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ Str::limit($department->description, 60) ?: '—' }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $department->users_count }}</td>
                                <td class="px-6 py-4">
                                    @if($department->status)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-500">{{ $department->created_at->format('M d, Y') }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('departments.show', $department->id) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100">View</a>
                                        <a href="{{ route('departments.edit', $department->id) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-teal-600 hover:bg-teal-50">Edit</a>
                                        <form action="{{ route('departments.destroy', $department->id) }}" method="POST" onsubmit="return confirm('Delete this department?')">@csrf @method('DELETE')
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">No departments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
