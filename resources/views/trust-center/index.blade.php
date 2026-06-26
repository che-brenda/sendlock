<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800 leading-tight">Trust Center</h2>
            <p class="text-sm text-slate-400">Your organization's centralized trust ecosystem</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ tab: 'trusted' }">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <!-- Tabs -->
            <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 text-sm font-medium shadow-sm">
                @php
                    $tabs = [
                        'trusted' => 'Trusted Domains',
                        'blocked' => 'Blocked Domains',
                        'recipients' => 'Verified Recipients',
                        'banking' => 'Vendor Bank Accounts',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                <button @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-teal-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        class="rounded-lg px-4 py-2">{{ $label }}</button>
                @endforeach
            </div>

            <!-- Trusted domains -->
            <div x-show="tab === 'trusted'" x-cloak class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-base font-semibold text-slate-800">Add Trusted Domain</h3>
                    <form method="POST" action="{{ route('trust-center.trusted-domains.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @csrf
                        <input type="text" name="domain" placeholder="vendor.com" required class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="vendor_name" placeholder="Vendor name (optional)" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add</button>
                    </form>
                </div>
                @include('trust-center.partials.list', [
                    'rows' => $trustedDomains,
                    'columns' => ['Domain' => 'domain', 'Vendor' => 'vendor_name'],
                    'route' => 'trust-center.trusted-domains.destroy',
                    'empty' => 'No trusted domains yet.',
                ])
            </div>

            <!-- Blocked domains -->
            <div x-show="tab === 'blocked'" x-cloak class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-base font-semibold text-slate-800">Add Blocked Domain</h3>
                    <form method="POST" action="{{ route('trust-center.blocked-domains.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @csrf
                        <input type="text" name="domain" placeholder="bad-domain.com" required class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="reason" placeholder="Reason (optional)" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">Block</button>
                    </form>
                </div>
                @include('trust-center.partials.list', [
                    'rows' => $blockedDomains,
                    'columns' => ['Domain' => 'domain', 'Reason' => 'reason'],
                    'route' => 'trust-center.blocked-domains.destroy',
                    'empty' => 'No blocked domains yet.',
                ])
            </div>

            <!-- Verified recipients -->
            <div x-show="tab === 'recipients'" x-cloak class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-base font-semibold text-slate-800">Add Verified Recipient</h3>
                    <form method="POST" action="{{ route('trust-center.verified-recipients.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        @csrf
                        <input type="email" name="email" placeholder="person@vendor.com" required class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="name" placeholder="Name (optional)" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="phone" placeholder="Phone (optional)" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add</button>
                    </form>
                </div>
                @include('trust-center.partials.list', [
                    'rows' => $verifiedRecipients,
                    'columns' => ['Email' => 'email', 'Name' => 'name', 'Phone' => 'phone'],
                    'route' => 'trust-center.verified-recipients.destroy',
                    'empty' => 'No verified recipients yet.',
                ])
            </div>

            <!-- Vendor bank accounts -->
            <div x-show="tab === 'banking'" x-cloak class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-1 text-base font-semibold text-slate-800">Add Vendor Bank Account</h3>
                    <p class="mb-4 text-sm text-slate-500">Known-good banking details. The risk engine flags emails whose banking details differ from these.</p>
                    <form method="POST" action="{{ route('trust-center.vendor-bank-accounts.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @csrf
                        <input type="text" name="vendor_domain" placeholder="vendor.com" required class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="vendor_name" placeholder="Vendor name" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="account_number" placeholder="Account number" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="iban" placeholder="IBAN" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <input type="text" name="swift" placeholder="SWIFT / BIC" class="rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add</button>
                    </form>
                </div>
                @include('trust-center.partials.list', [
                    'rows' => $vendorBankAccounts,
                    'columns' => ['Vendor' => 'vendor_name', 'Domain' => 'vendor_domain', 'Account' => 'account_number', 'IBAN' => 'iban'],
                    'route' => 'trust-center.vendor-bank-accounts.destroy',
                    'empty' => 'No vendor bank accounts yet.',
                ])
            </div>

        </div>
    </div>
</x-app-layout>
