<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold text-slate-900">Create your organization</h1>
        <p class="mt-1 text-sm text-slate-500">
            Set up SendLock to protect your outbound communications. This creates your
            organization and its first administrator account.
        </p>
    </div>

    @php
        $industries = [
            'Banking & Finance',
            'Insurance',
            'Logistics & Freight',
            'Legal',
            'Procurement & Supply Chain',
            'Healthcare',
            'Technology',
            'Government',
            'Other',
        ];
        $field = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        <!-- Organization -->
        <div>
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Organization</p>
            <div class="space-y-4">
                <div>
                    <label for="organization_name" class="{{ $label }}">Organization name</label>
                    <input id="organization_name" name="organization_name" type="text"
                           value="{{ old('organization_name') }}" required autofocus
                           autocomplete="organization" class="{{ $field }}">
                    <x-input-error :messages="$errors->get('organization_name')" class="mt-2" />
                </div>

                <div>
                    <label for="industry" class="{{ $label }}">Industry</label>
                    <select id="industry" name="industry" required class="{{ $field }}">
                        <option value="" disabled {{ old('industry') ? '' : 'selected' }}>Select your industry</option>
                        @foreach($industries as $industry)
                            <option value="{{ $industry }}" @selected(old('industry') === $industry)>{{ $industry }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Tailors fraud detection to your sector's common attack patterns.</p>
                    <x-input-error :messages="$errors->get('industry')" class="mt-2" />
                </div>
            </div>
        </div>

        <!-- Administrator account -->
        <div>
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Administrator account</p>
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="{{ $label }}">First name</label>
                        <input id="first_name" name="first_name" type="text"
                               value="{{ old('first_name') }}" required autocomplete="given-name" class="{{ $field }}">
                        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="last_name" class="{{ $label }}">Last name</label>
                        <input id="last_name" name="last_name" type="text"
                               value="{{ old('last_name') }}" required autocomplete="family-name" class="{{ $field }}">
                        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label for="email" class="{{ $label }}">Work email</label>
                    <input id="email" name="email" type="email"
                           value="{{ old('email') }}" required autocomplete="username" class="{{ $field }}">
                    <p class="mt-1 text-xs text-slate-400">Use your corporate domain — it becomes your organization's primary contact.</p>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="{{ $label }}">Password</label>
                        <input id="password" name="password" type="password"
                               required autocomplete="new-password" class="{{ $field }}">
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               required autocomplete="new-password" class="{{ $field }}">
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>
                </div>
            </div>
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
            Create organization
        </button>

        <p class="text-center text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="font-medium text-teal-600 hover:text-teal-700">Sign in</a>
        </p>
    </form>
</x-guest-layout>
