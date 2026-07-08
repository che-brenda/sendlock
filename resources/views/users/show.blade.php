<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">User Details</h2>
            <p class="text-sm text-slate-400">{{ $user->worker_number ?? '—' }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Back to users
            </a>

            <!-- Identity card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-600 text-lg font-semibold text-white">
                        {{ $user->initials }}
                    </span>
                    <div>
                        <p class="text-lg font-semibold text-slate-900">{{ $user->display_name }}</p>
                        <p class="text-sm text-slate-500">{{ $user->job_title ?? 'No title' }}</p>
                    </div>
                    <div class="ml-auto">
                        @if($user->status)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Inactive</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($user->hasPendingTemporaryPassword())
            <!-- Temporary credential (only until the user signs in and sets their own) -->
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6.75a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25v-6.75a2.25 2.25 0 012.25-2.25z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-amber-900">Temporary password</p>
                        <p class="mt-0.5 text-xs text-amber-700">Share this with the user. It will disappear once they sign in and set their own password.</p>
                        <x-temporary-password :user="$user" class="mt-3 !px-3 !py-1.5" />
                    </div>
                </div>
            </div>
            @endif

            <!-- Details -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $rows = [
                            ['User ID (system)', '#' . $user->id],
                            ['Worker number', $user->worker_number ?? '—'],
                            ['Email', $user->email],
                            ['Phone', $user->phone ?? '—'],
                            ['Department', $user->department?->department_name ?? 'Not assigned'],
                            ['Role', $user->getRoleNames()->first() ?? '—'],
                            ['Organization', $user->organization?->organization_name ?? '—'],
                            ['Last login', $user->last_login?->format('M d, Y H:i') ?? 'Never'],
                            ['Created', $user->created_at->format('M d, Y H:i')],
                        ];
                    @endphp
                    @foreach($rows as [$k, $v])
                    <div class="flex justify-between px-6 py-3">
                        <dt class="text-slate-500">{{ $k }}</dt>
                        <dd class="font-medium text-slate-800">{{ $v }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('users.edit', $user->id) }}" class="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white hover:bg-teal-700">Edit User</a>
            </div>

        </div>
    </div>
</x-app-layout>
