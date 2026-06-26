<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">{{ $department->department_name }}</h2>
            <p class="text-sm text-slate-400">Department details</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            <a href="{{ route('departments.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Back to departments
            </a>

            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Members</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $department->users_count }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Status</p>
                    <p class="mt-2">
                        @if($department->status)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Inactive</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4"><h3 class="text-base font-semibold text-slate-800">Details</h3></div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="px-6 py-3">
                        <dt class="text-slate-500">Description</dt>
                        <dd class="mt-1 text-slate-800">{{ $department->description ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Created</dt>
                        <dd class="font-medium text-slate-800">{{ $department->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Members -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4"><h3 class="text-base font-semibold text-slate-800">Members</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @forelse($department->users as $member)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-600 text-xs font-semibold text-white">{{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}</span>
                                        <span class="font-medium text-slate-800">{{ $member->first_name }} {{ $member->last_name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-slate-600">{{ $member->job_title ?? '—' }}</td>
                                <td class="px-6 py-3 text-right text-slate-500">{{ $member->email }}</td>
                            </tr>
                            @empty
                            <tr><td class="px-6 py-8 text-center text-slate-400">No members in this department yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('departments.edit', $department->id) }}" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700">Edit Department</a>
            </div>

        </div>
    </div>
</x-app-layout>
