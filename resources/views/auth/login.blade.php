<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold text-slate-900">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to your SendLock workspace.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @php
        $field = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="{{ $label }}">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username" class="{{ $field }}">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="{{ $label }}">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-teal-600 hover:text-teal-700">
                        Forgot password?
                    </a>
                @endif
            </div>
            <input id="password" name="password" type="password"
                   required autocomplete="current-password" class="{{ $field }}">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember_me" class="flex items-center">
            <input id="remember_me" name="remember" type="checkbox"
                   class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
            <span class="ms-2 text-sm text-slate-600">Remember me</span>
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
            Sign in
        </button>

        <p class="text-center text-sm text-slate-500">
            New to SendLock?
            <a href="{{ route('register') }}" class="font-medium text-teal-600 hover:text-teal-700">Create your organization</a>
        </p>
    </form>
</x-guest-layout>
