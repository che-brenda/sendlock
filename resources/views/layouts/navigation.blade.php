@php
    $user = Auth::user();

    $isSuper     = $user->isSuperAdmin();
    $isHead      = $user->isHeadOrgAdmin();
    $isOrgAdmin  = $user->isOrgAdmin();
    $isAdminLevel = $isSuper || $isHead || $isOrgAdmin;
    $canApprove   = $isAdminLevel || $user->hasRole('Manager');
    $canAudit     = $isAdminLevel || $user->hasAnyRole(['Security Officer', 'Auditor']);

    // Heroicon outline path data, keyed by name.
    $icon = [
        'home'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.5a.75.75 0 00.75.75h4.5a.75.75 0 00.75-.75V15a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v5.25a.75.75 0 00.75.75h4.5a.75.75 0 00.75-.75V9.75" />',
        'building'=> '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125V21" />',
        'globe'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247" />',
        'card'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />',
        'envelope'=> '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />',
        'library' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />',
        'phone'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />',
        'check'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />',
        'users'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
        'cog'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        'report'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />',
        'doc'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />',
    ];
@endphp

<aside x-cloak
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-64 transform flex-col bg-gradient-to-b from-slate-900 to-slate-950 transition duration-200 ease-in-out lg:translate-x-0">

    <!-- Brand -->
    <div class="flex h-16 items-center gap-2.5 border-b border-slate-800 px-5">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-teal-700">
            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </span>
        <span class="text-lg font-bold tracking-tight text-white">SendLock</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">

        <!-- Overview -->
        <div class="space-y-1">
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Overview</p>
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" :icon="$icon['home']">
                Dashboard
            </x-sidebar-link>
        </div>

        @if($isSuper)
        <!-- Platform (Super Admin) -->
        <div class="space-y-1">
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Platform</p>
            <x-sidebar-link :href="route('organizations.index')" :active="request()->routeIs('organizations.*')" :icon="$icon['building']">
                Organizations
            </x-sidebar-link>
            <x-sidebar-link :href="route('threat-intel.index')" :active="request()->routeIs('threat-intel.*')" :icon="$icon['globe']">
                Global Threat Intel
            </x-sidebar-link>
            <x-sidebar-link :href="route('billing.index')" :active="request()->routeIs('billing.*')" :icon="$icon['card']">
                Plans &amp; Billing
            </x-sidebar-link>
        </div>
        @endif

        <!-- Security operations -->
        <div class="space-y-1">
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Security Operations</p>
            <x-sidebar-link :href="route('protected-email.create')" :active="request()->routeIs('protected-email.*')" :icon="$icon['check']">
                Send Protected
            </x-sidebar-link>
            <x-sidebar-link :href="route('threat.overview')" :active="request()->routeIs('threat.overview')" :icon="$icon['warning']">
                Threat Overview
            </x-sidebar-link>
            <x-sidebar-link :href="route('email-scans.index')" :active="request()->routeIs('email-scans.*')" :icon="$icon['envelope']">
                Email Scans
            </x-sidebar-link>
            <x-sidebar-link :href="route('blocked-attempts.index')" :active="request()->routeIs('blocked-attempts.*')" :icon="$icon['warning']">
                Blocked Attempts
            </x-sidebar-link>
            @if($isAdminLevel)
            <x-sidebar-link :href="route('flagged-domains.index')" :active="request()->routeIs('flagged-domains.*')" :icon="$icon['globe']">
                Flagged Domains
            </x-sidebar-link>
            @endif
            @if($isAdminLevel)
            <x-sidebar-link :href="route('trust-center.index')" :active="request()->routeIs('trust-center.*')" :icon="$icon['library']">
                Trust Center
            </x-sidebar-link>
            @endif
            <x-sidebar-link :href="route('recipient-verification.index')" :active="request()->routeIs('recipient-verification.*')" :icon="$icon['phone']">
                Recipient Verification
            </x-sidebar-link>
            @if($canApprove)
            <x-sidebar-link :href="route('approvals.index')" :active="request()->routeIs('approvals.*')" :icon="$icon['check']">
                Approvals
            </x-sidebar-link>
            @endif
        </div>

        @if($isAdminLevel)
        <!-- Administration -->
        <div class="space-y-1">
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Administration</p>
            @if($isHead || $isSuper)
            <x-sidebar-link :href="route('sub-organizations.index')" :active="request()->routeIs('sub-organizations.*')" :icon="$icon['building']">
                Sub-Organizations
            </x-sidebar-link>
            @endif
            <x-sidebar-link :href="route('users.index')" :active="request()->routeIs('users.*')" :icon="$icon['users']">
                Users &amp; Workers
            </x-sidebar-link>
            <x-sidebar-link :href="route('departments.index')" :active="request()->routeIs('departments.*')" :icon="$icon['library']">
                Departments
            </x-sidebar-link>
            <x-sidebar-link :href="route('policies.index')" :active="request()->routeIs('policies.*')" :icon="$icon['cog']">
                Policies
            </x-sidebar-link>
        </div>
        @endif

        @if($canAudit)
        <!-- Insights -->
        <div class="space-y-1">
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Insights</p>
            <x-sidebar-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" :icon="$icon['report']">
                Reports
            </x-sidebar-link>
            <x-sidebar-link :href="route('audit-logs.index')" :active="request()->routeIs('audit-logs.*')" :icon="$icon['doc']">
                Audit Log
            </x-sidebar-link>
        </div>
        @endif
    </nav>

    <!-- Org footer -->
    <div class="border-t border-slate-800 px-5 py-4">
        <p class="text-xs font-medium text-slate-400">
            {{ $user->organization?->organization_name ?? 'SendLock Platform' }}
        </p>
        <p class="text-xs text-slate-600">
            {{ $user->worker_number ? $user->worker_number . ' · ' : '' }}{{ ucfirst($user->organization?->type ?? 'platform') }}
        </p>
    </div>
</aside>
