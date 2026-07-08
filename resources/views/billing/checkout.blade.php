<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Checkout</h2>
            <p class="text-sm text-slate-400">Complete payment to activate your subscription</p>
        </div>
    </x-slot>

    @php
        $field = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <a href="{{ route('billing.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-teal-600 hover:text-teal-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Back to plans
            </a>

            <form method="POST" action="{{ route('billing.process', $key) }}"
                  x-data="{ method: @js(old('payment_method', 'visa')) }"
                  class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @csrf

                <!-- Payment panel -->
                <div class="space-y-6 lg:col-span-2">

                    <!-- Method picker -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-semibold text-slate-800">Payment method</p>
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @php
                                $methodMeta = [
                                    'visa' => ['label' => 'Card', 'sub' => 'Visa · Mastercard'],
                                    'mtn_momo' => ['label' => 'MTN MoMo', 'sub' => 'Mobile Money'],
                                    'paypal' => ['label' => 'PayPal', 'sub' => 'Wallet'],
                                ];
                            @endphp
                            @foreach($methods as $mKey => $m)
                            <label :class="method === '{{ $mKey }}' ? 'border-teal-500 ring-2 ring-teal-500 bg-teal-50' : 'border-slate-200 hover:border-slate-300'"
                                   class="flex cursor-pointer flex-col gap-1 rounded-xl border bg-white p-3 transition">
                                <input type="radio" name="payment_method" value="{{ $mKey }}" x-model="method" class="sr-only">
                                <span class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-slate-800">{{ $methodMeta[$mKey]['label'] ?? $m['name'] }}</span>
                                    <svg x-show="method === '{{ $mKey }}'" x-cloak class="h-4 w-4 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                </span>
                                <span class="text-xs text-slate-400">{{ $methodMeta[$mKey]['sub'] ?? '' }}</span>
                            </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('payment_method')" class="mt-3" />
                    </div>

                    <!-- Card fields -->
                    <div x-show="method === 'visa'" x-cloak class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-semibold text-slate-800">Card details</p>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label for="card_name" class="{{ $label }}">Name on card</label>
                                <input id="card_name" name="card_name" type="text" value="{{ old('card_name') }}" placeholder="Jane Doe" class="{{ $field }}">
                                <x-input-error :messages="$errors->get('card_name')" class="mt-1.5" />
                            </div>
                            <div>
                                <label for="card_number" class="{{ $label }}">Card number</label>
                                <input id="card_number" name="card_number" type="text" inputmode="numeric" value="{{ old('card_number') }}"
                                       placeholder="4242 4242 4242 4242" maxlength="23" class="{{ $field }}">
                                <x-input-error :messages="$errors->get('card_number')" class="mt-1.5" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="card_expiry" class="{{ $label }}">Expiry</label>
                                    <input id="card_expiry" name="card_expiry" type="text" value="{{ old('card_expiry') }}" placeholder="MM/YY" maxlength="5" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('card_expiry')" class="mt-1.5" />
                                </div>
                                <div>
                                    <label for="card_cvv" class="{{ $label }}">CVV</label>
                                    <input id="card_cvv" name="card_cvv" type="text" inputmode="numeric" value="{{ old('card_cvv') }}" placeholder="123" maxlength="4" class="{{ $field }}">
                                    <x-input-error :messages="$errors->get('card_cvv')" class="mt-1.5" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MTN Mobile Money fields -->
                    <div x-show="method === 'mtn_momo'" x-cloak class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-semibold text-slate-800">MTN Mobile Money</p>
                        <p class="mt-1 text-xs text-slate-400">You'll receive a prompt on your phone to approve the payment.</p>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label for="momo_name" class="{{ $label }}">Account name</label>
                                <input id="momo_name" name="momo_name" type="text" value="{{ old('momo_name') }}" placeholder="Jane Doe" class="{{ $field }}">
                                <x-input-error :messages="$errors->get('momo_name')" class="mt-1.5" />
                            </div>
                            <div>
                                <label for="momo_phone" class="{{ $label }}">MTN number</label>
                                <input id="momo_phone" name="momo_phone" type="tel" value="{{ old('momo_phone') }}" placeholder="+233 24 123 4567" class="{{ $field }}">
                                <x-input-error :messages="$errors->get('momo_phone')" class="mt-1.5" />
                            </div>
                        </div>
                    </div>

                    <!-- PayPal -->
                    <div x-show="method === 'paypal'" x-cloak class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-semibold text-slate-800">PayPal</p>
                        <p class="mt-1 text-xs text-slate-400">Enter the email tied to your PayPal account. You'll confirm the payment with PayPal.</p>
                        <div class="mt-4">
                            <label for="paypal_email" class="{{ $label }}">PayPal email</label>
                            <input id="paypal_email" name="paypal_email" type="email" value="{{ old('paypal_email') }}" placeholder="you@example.com" class="{{ $field }}">
                            <x-input-error :messages="$errors->get('paypal_email')" class="mt-1.5" />
                        </div>
                    </div>

                    <p class="flex items-center gap-2 text-xs text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5a2.25 2.25 0 0 1 2.25 2.25v6.75a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25v-6.75a2.25 2.25 0 0 1 2.25-2.25Z" /></svg>
                        Payments are encrypted. Card and account details are never stored on our servers.
                    </p>
                </div>

                <!-- Order summary -->
                <div class="lg:col-span-1">
                    <div class="sticky top-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-semibold text-slate-800">Order summary</p>

                        <div class="mt-4 flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $package['name'] }}</p>
                                <p class="text-xs text-slate-400">Monthly subscription</p>
                            </div>
                            <p class="font-semibold text-slate-900">{{ $symbol }}{{ $package['price'] }}</p>
                        </div>

                        <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                            <div class="flex justify-between text-slate-500">
                                <span>Subtotal</span><span>{{ $symbol }}{{ $package['price'] }}.00</span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>Billing cycle</span><span>Monthly</span>
                            </div>
                            <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-slate-900">
                                <span>Total due today</span><span>{{ $symbol }}{{ $package['price'] }}.00</span>
                            </div>
                        </div>

                        <button type="submit"
                                class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700">
                            Pay {{ $symbol }}{{ $package['price'] }}.00
                        </button>
                        <p class="mt-3 text-center text-xs text-slate-400">By paying you agree to the <a href="{{ route('terms') }}" target="_blank" class="font-medium text-teal-600 hover:text-teal-700">Terms &amp; Conditions</a>.</p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
