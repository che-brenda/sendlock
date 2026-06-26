<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">{{ $subOrganization->organization_name }}</h2>
            <p class="text-sm text-slate-400">Sub-organization of {{ $head->organization_name }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            <a href="{{ route('sub-organizations.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                Back to sub-organizations
            </a>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Users</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $subOrganization->users_count }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Departments</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $subOrganization->departments_count }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Details</h3>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Industry</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->industry ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Phone</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Subscription Plan</dt>
                        <dd class="font-medium text-slate-800">{{ $subOrganization->subscription_plan }}</dd>
                    </div>
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            @if($subOrganization->status)
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Inactive</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

        </div>
    </div>
</x-app-layout>
