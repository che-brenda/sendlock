<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

```
    <!-- Organization Name -->
    <div>
        <x-input-label for="organization_name" :value="__('Organization Name')" />
        <x-text-input id="organization_name" class="block mt-1 w-full"
            type="text"
            name="organization_name"
            :value="old('organization_name')"
            required autofocus />
        <x-input-error :messages="$errors->get('organization_name')" class="mt-2" />
    </div>

    <!-- Industry -->
    <div class="mt-4">
        <x-input-label for="industry" :value="__('Industry')" />
        <x-text-input id="industry" class="block mt-1 w-full"
            type="text"
            name="industry"
            :value="old('industry')"
            required />
        <x-input-error :messages="$errors->get('industry')" class="mt-2" />
    </div>

    <!-- First Name -->
    <div class="mt-4">
        <x-input-label for="first_name" :value="__('First Name')" />
        <x-text-input id="first_name" class="block mt-1 w-full"
            type="text"
            name="first_name"
            :value="old('first_name')"
            required />
        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
    </div>

    <!-- Last Name -->
    <div class="mt-4">
        <x-input-label for="last_name" :value="__('Last Name')" />
        <x-text-input id="last_name" class="block mt-1 w-full"
            type="text"
            name="last_name"
            :value="old('last_name')"
            required />
        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
    </div>

    <!-- Email -->
    <div class="mt-4">
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" class="block mt-1 w-full"
            type="email"
            name="email"
            :value="old('email')"
            required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <!-- Password -->
    <div class="mt-4">
        <x-input-label for="password" :value="__('Password')" />
        <x-text-input id="password" class="block mt-1 w-full"
            type="password"
            name="password"
            required />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <!-- Confirm Password -->
    <div class="mt-4">
        <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
        <x-text-input id="password_confirmation" class="block mt-1 w-full"
            type="password"
            name="password_confirmation"
            required />
        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
    </div>

    <div class="flex items-center justify-end mt-4">
        <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md"
           href="{{ route('login') }}">
            {{ __('Already registered?') }}
        </a>

        <x-primary-button class="ms-4">
            {{ __('Register Organization') }}
        </x-primary-button>
    </div>
</form>
```

</x-guest-layout>
