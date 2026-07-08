<x-app-layout>
    <x-slot name="header">
        <div>
            @if(($currentOrg ?? null))
                @if($currentOrg->parent)
                    {{-- Sub-organization: main organization on top, sub-org below it. --}}
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $currentOrg->parent->organization_name }}</p>
                    <h2 class="flex items-center gap-2 text-xl font-semibold text-slate-800 leading-tight">
                        <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                        {{ $currentOrg->organization_name }}
                    </h2>
                    <p class="text-sm text-slate-400">Security Dashboard · sub-organization</p>
                @else
                    <h2 class="text-xl font-semibold text-slate-800 leading-tight">{{ $currentOrg->organization_name }}</h2>
                    <p class="text-sm text-slate-400">Security Dashboard</p>
                @endif
            @else
                <h2 class="text-xl font-semibold text-slate-800 leading-tight">All Organizations</h2>
                <p class="text-sm text-slate-400">Platform security dashboard</p>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            @php
                $viewer = auth()->user();
                $showSubscription = ($currentOrg ?? null) && ($viewer->isOrgAdmin() || $viewer->isHeadOrgAdmin());
            @endphp
            @if($showSubscription)
            @php
                $subState = $currentOrg->subscriptionState();
                $subDays = $currentOrg->daysUntilExpiry();
            @endphp
            <!-- Subscription status (org can check its billing at a glance) -->
            <div @class([
                'flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-white p-5 shadow-sm',
                'border-rose-200' => $subState === 'expired',
                'border-amber-200' => in_array($subState, ['expiring_soon', 'pending'], true),
                'border-slate-200' => ! in_array($subState, ['expired', 'expiring_soon', 'pending'], true),
            ])>
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-800">{{ $currentOrg->planLabel() }} plan</p>
                            <x-subscription-badge :organization="$currentOrg" />
                        </div>
                        <p class="mt-0.5 text-sm text-slate-500">
                            @switch($subState)
                                @case('expired') Subscription expired on {{ $currentOrg->subscription_expires_at?->format('M d, Y') }}. @break
                                @case('expiring_soon') Renews in {{ $subDays }} {{ \Illuminate\Support\Str::plural('day', $subDays) }} ({{ $currentOrg->subscription_expires_at?->format('M d, Y') }}). @break
                                @case('active') Renews {{ $currentOrg->subscription_expires_at?->format('M d, Y') }}. @break
                                @case('free') You're on the Free plan — upgrade anytime. @break
                                @case('pending') Awaiting payment — choose a package to activate. @break
                                @default No active subscription.
                            @endswitch
                        </p>
                    </div>
                </div>
                <a href="{{ route('subscription.index') }}"
                   @class([
                       'shrink-0 rounded-lg px-4 py-2 text-sm font-semibold transition',
                       'bg-rose-600 text-white hover:bg-rose-700' => $subState === 'expired',
                       'bg-amber-600 text-white hover:bg-amber-700' => in_array($subState, ['expiring_soon', 'pending'], true),
                       'bg-slate-900 text-white hover:bg-slate-700' => ! in_array($subState, ['expired', 'expiring_soon', 'pending'], true),
                   ])>
                    {{ in_array($subState, ['expired', 'expiring_soon'], true) ? 'Renew' : 'View subscription' }}
                </a>
            </div>
            @endif

            <!-- Stat cards (link to the relevant management pages where accessible) -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                @php
                    $u = auth()->user();
                    $isAdminLevel = $u->isSuperAdmin() || $u->isHeadOrgAdmin() || $u->isOrgAdmin();
                    $orgHref = $u->isSuperAdmin()
                        ? route('organizations.index')
                        : ($u->canManageSubOrganizations() ? route('sub-organizations.index') : null);
                    $usersHref = $isAdminLevel ? route('users.index') : null;
                    $deptHref = $isAdminLevel ? route('departments.index') : null;

                    $cards = [
                        ['label' => 'Organizations', 'value' => $organizations, 'accent' => 'text-slate-900', 'href' => $orgHref],
                        ['label' => 'Users', 'value' => $users, 'accent' => 'text-slate-900', 'href' => $usersHref],
                        ['label' => 'Departments', 'value' => $departments, 'accent' => 'text-slate-900', 'href' => $deptHref],
                        ['label' => 'Active Users', 'value' => $activeUsers, 'accent' => 'text-emerald-600', 'href' => $usersHref],
                        ['label' => 'Inactive Users', 'value' => $inactiveUsers, 'accent' => 'text-rose-600', 'href' => $usersHref],
                    ];
                @endphp

                @foreach($cards as $card)
                    @if($card['href'])
                    <a href="{{ $card['href'] }}" class="group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300 hover:shadow-md">
                        <p class="flex items-center justify-between text-xs font-medium uppercase tracking-wide text-slate-400">
                            {{ $card['label'] }}
                            <svg class="h-3.5 w-3.5 text-slate-300 transition group-hover:text-teal-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </p>
                        <p class="mt-2 text-3xl font-bold {{ $card['accent'] }}">{{ $card['value'] }}</p>
                    </a>
                    @else
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold {{ $card['accent'] }}">{{ $card['value'] }}</p>
                    </div>
                    @endif
                @endforeach
            </div>

            @if(($aggregatesSubOrgs ?? false))
            <p class="-mt-4 text-xs text-slate-400">Totals above include this organization and its sub-organizations.</p>
            @endif

            <!-- Top risk reasons (data-driven from this org's scan history) -->
            @isset($riskReasons)
            <x-risk-chart :segments="$riskReasons" :total="$riskTotal" :size="220" title="Top Risk Reasons" />
            @endisset

            <!-- Sub-Organizations (head organization view) -->
            @if(($subOrganizations ?? collect())->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Sub-Organizations</h3>
                        <p class="text-sm text-slate-400">Each operates as its own organization; you see everything beneath you.</p>
                    </div>
                    <a href="{{ route('sub-organizations.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">Manage</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Sub-Organization</th>
                                <th class="px-6 py-3">Users</th>
                                <th class="px-6 py-3">Departments</th>
                                <th class="px-6 py-3">Scans</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($subOrganizations as $sub)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $sub->organization_name }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $sub->users_count }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $sub->departments_count }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $sub->email_scans_count }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $sub->status ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $sub->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if($canDrillDown ?? false)
                                    <a href="{{ route('sub-organizations.show', $sub) }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">View activity</a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Sub-organization empty state (head org admin, none created yet) -->
            @if(($canDrillDown ?? false) && optional($currentOrg ?? null)->isHead() && ($subOrganizations ?? collect())->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm">
                <p class="text-base font-semibold text-slate-700">No sub-organizations yet</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">Add branches, subsidiaries, or teams that run on their own organization — you'll see everything happening beneath you, right here.</p>
                <a href="{{ route('sub-organizations.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Create sub-organization
                </a>
            </div>
            @endif

            <!-- Organizations & sub-organizations (Super Admin) -->
            @if(($headOrganizations ?? collect())->isNotEmpty())
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Organizations &amp; Sub-Organizations</h3>
                    <a href="{{ route('organizations.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">Manage</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Organization</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Sub-Organizations</th>
                                <th class="px-6 py-3">Plan</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($headOrganizations as $head)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-3 font-medium text-slate-800">{{ $head->organization_name }}</td>
                                <td class="px-6 py-3 text-slate-500">Head</td>
                                <td class="px-6 py-3 text-slate-600">{{ $head->children_count }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $head->subscription_plan }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $head->status ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $head->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Recent security activity -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Recent Security Activity</h3>
                    <a href="{{ route('audit-logs.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">View all</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentLogs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ $log->action }}</span>
                                </td>
                                <td class="px-6 py-3 text-slate-700">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $log->description }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-slate-400">No activity recorded yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
