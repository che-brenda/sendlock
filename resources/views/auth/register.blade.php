<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold text-slate-900">Create your organization</h1>
        <p class="mt-1 text-sm text-slate-500">
            Register your organization on SendLock to protect its outbound communications.
            This creates your organization and its admin login.
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

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Organization -->
        <div class="space-y-3">
            <div>
                <label for="organization_name" class="sr-only">Organization name</label>
                <input id="organization_name" name="organization_name" type="text" placeholder="Organization name"
                       value="{{ old('organization_name') }}" required autofocus
                       autocomplete="organization" class="{{ $field }}">
                <x-input-error :messages="$errors->get('organization_name')" class="mt-1.5" />
            </div>

            <div>
                <label for="industry" class="sr-only">Industry</label>
                <select id="industry" name="industry" required class="{{ $field }} text-slate-500">
                    <option value="" disabled {{ old('industry') ? '' : 'selected' }}>Select your industry</option>
                    @foreach($industries as $industry)
                        <option value="{{ $industry }}" @selected(old('industry') === $industry)>{{ $industry }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('industry')" class="mt-1.5" />
            </div>
        </div>

        <!-- Admin login -->
        <div class="space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="email" class="sr-only">Work email</label>
                    <input id="email" name="email" type="email" placeholder="Email"
                           value="{{ old('email') }}" required autocomplete="username" class="{{ $field }}">
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>
                <div>
                    <label for="phone" class="sr-only">Phone number</label>
                    @php $selectedDial = old('country_code', config('countries.default')); @endphp
                    <div class="flex rounded-lg shadow-sm">
                        <select name="country_code" aria-label="Country dial code"
                                class="w-14 shrink-0 rounded-l-lg border-slate-300 focus:z-10 focus:border-teal-500 focus:ring-teal-500">
                            @foreach(config('countries.list') as $c)
                                @php $flag = collect(str_split($c['iso']))->map(fn ($ch) => mb_chr(0x1F1E6 + ord($ch) - ord('A')))->implode(''); @endphp
                                <option value="{{ $c['dial'] }}" @selected($selectedDial === $c['dial'])>{{ $flag }} {{ $c['dial'] }} {{ $c['name'] }}</option>
                            @endforeach
                        </select>
                        <input id="phone" name="phone" type="tel" placeholder="Phone number"
                               value="{{ old('phone') }}" required autocomplete="tel"
                               class="-ml-px block w-full rounded-r-lg border-slate-300 focus:z-10 focus:border-teal-500 focus:ring-teal-500">
                    </div>
                    <x-input-error :messages="$errors->get('country_code')" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" placeholder="Password"
                           required autocomplete="new-password" class="{{ $field }}">
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                </div>
                <div>
                    <label for="password_confirmation" class="sr-only">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirm password"
                           required autocomplete="new-password" class="{{ $field }}">
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
                </div>
            </div>
        </div>

        <!-- Terms & Conditions -->
        <div>
            <label for="terms" class="flex items-start gap-2.5">
                <input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms'))
                       class="mt-0.5 rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                <span class="text-sm text-slate-600">
                    I agree to SendLock's
                    <a href="{{ route('terms') }}" target="_blank" rel="noopener"
                       class="font-medium text-teal-600 hover:text-teal-700">Terms &amp; Conditions</a>.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
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
