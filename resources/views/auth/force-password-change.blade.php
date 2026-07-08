<x-guest-layout>
    <div class="mb-6 text-center">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6.75a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25v-6.75a2.25 2.25 0 012.25-2.25z" />
            </svg>
        </span>
        <h1 class="mt-3 text-xl font-bold text-slate-900">Set your password</h1>
        <p class="mt-1 text-sm text-slate-500">
            @if($organization)
                Welcome to <span class="font-semibold text-slate-700">{{ $organization->organization_name }}</span>.
            @endif
            For your security, choose a new password before continuing.
        </p>
    </div>

    @php
        $field = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <form method="POST" action="{{ route('password.first-change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="password" class="{{ $label }}">New password</label>
            <input id="password" name="password" type="password" required autofocus
                   autocomplete="new-password" class="{{ $field }}">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="password_confirmation" class="{{ $label }}">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" class="{{ $field }}">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
            Save password &amp; continue
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-xs font-medium text-slate-400 hover:text-slate-600">Sign out instead</button>
    </form>
</x-guest-layout>
