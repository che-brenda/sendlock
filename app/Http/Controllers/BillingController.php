<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Subscription billing. After registration an organization is held here (by the
 * EnsureSubscribed middleware) until it pays for one of the configured packages.
 * The payment step is a stub — no real gateway is called — but it records a
 * Payment, grants the package's plan entitlements, and lifts the billing gate.
 */
class BillingController extends Controller
{
    public function index(): View
    {
        return view('billing.index', [
            'packages' => config('sendlock.billing.packages'),
            'symbol' => config('sendlock.billing.currency_symbol'),
            'organization' => auth()->user()->organization,
        ]);
    }

    /**
     * The organization's own subscription detail page (plan, dates, expiry,
     * payment history). Linked from the org sidebar.
     */
    public function subscription(): View|RedirectResponse
    {
        $organization = auth()->user()->organization;

        // The platform owner has no org of its own — send them to the
        // customer-billing overview instead.
        if (! $organization) {
            return redirect()->route('billing.customers');
        }

        return view('billing.subscription', [
            'organization' => $organization,
            'package' => $this->packageForPlan($organization?->subscription_plan),
            'symbol' => config('sendlock.billing.currency_symbol'),
            'payments' => $organization
                ? $organization->payments()->latest('paid_at')->latest('id')->get()
                : collect(),
        ]);
    }

    /**
     * Super Admin (product owner) view of every customer's billing status:
     * plan, status, dates, last payment, and platform revenue totals.
     */
    public function customers(): View
    {
        $organizations = Organization::query()
            ->with(['parent', 'payments' => fn ($q) => $q->latest('paid_at')->latest('id')])
            ->withSum('payments as paid_total', 'amount')
            ->orderByDesc('subscribed_at')
            ->orderByDesc('id')
            ->get();

        return view('billing.customers', [
            'organizations' => $organizations,
            'symbol' => config('sendlock.billing.currency_symbol'),
            'stats' => [
                'active' => $organizations->where('subscription_status', 'active')->count(),
                'pending' => $organizations->where('subscription_status', 'pending')->count(),
                'revenue' => (float) $organizations->sum('paid_total'),
            ],
        ]);
    }

    public function checkout(string $package): View|RedirectResponse
    {
        $packageData = $this->resolvePackage($package);

        // The Free plan needs no payment — send it through the no-charge flow.
        if ($this->isFree($packageData)) {
            return redirect()->route('billing.index');
        }

        return view('billing.checkout', [
            'key' => $package,
            'package' => $packageData,
            'methods' => config('sendlock.billing.payment_methods'),
            'symbol' => config('sendlock.billing.currency_symbol'),
        ]);
    }

    /**
     * Activate the Free plan without payment. No Payment record is created.
     */
    public function selectFree(): RedirectResponse
    {
        $free = config('sendlock.billing.packages.free');
        $organization = auth()->user()->organization;

        $organization->update([
            'subscription_plan' => $free['plan'],
            'subscription_status' => 'active',
            'subscribed_at' => Carbon::now(),
            'subscription_expires_at' => null,   // Free never expires.
        ]);

        AuditLogger::log(
            'SUBSCRIPTION_ACTIVATED',
            'ORGANIZATION',
            $organization->id,
            'Activated the Free plan'
        );

        return redirect()
            ->route('dashboard')
            ->with('success', "You're on the Free plan — welcome to SendLock. Upgrade anytime from Billing.");
    }

    public function process(Request $request, string $package): RedirectResponse
    {
        $packageData = $this->resolvePackage($package);

        // Free can't be "paid for" — route it through the no-charge activation.
        if ($this->isFree($packageData)) {
            return $this->selectFree();
        }

        $methods = config('sendlock.billing.payment_methods');

        $request->validate([
            'payment_method' => ['required', Rule::in(array_keys($methods))],

            // Card (Visa / Mastercard)
            'card_name' => ['nullable', 'required_if:payment_method,visa', 'string', 'max:255'],
            'card_number' => ['nullable', 'required_if:payment_method,visa', 'string', 'regex:/^[0-9 ]{13,23}$/'],
            'card_expiry' => ['nullable', 'required_if:payment_method,visa', 'regex:#^(0[1-9]|1[0-2])/\d{2}$#'],
            'card_cvv' => ['nullable', 'required_if:payment_method,visa', 'digits_between:3,4'],

            // MTN Mobile Money
            'momo_name' => ['nullable', 'required_if:payment_method,mtn_momo', 'string', 'max:255'],
            'momo_phone' => ['nullable', 'required_if:payment_method,mtn_momo', 'string', 'max:30', 'regex:/^[0-9+ ]{7,20}$/'],

            // PayPal
            'paypal_email' => ['nullable', 'required_if:payment_method,paypal', 'email'],
        ], [
            'card_number.regex' => 'Enter a valid card number.',
            'card_expiry.regex' => 'Use MM/YY for the expiry date.',
            'momo_phone.regex' => 'Enter a valid mobile-money number.',
        ]);

        $organization = auth()->user()->organization;
        $symbol = config('sendlock.billing.currency_symbol');

        // --- Stubbed payment capture (no real gateway is contacted) -----------
        $payment = Payment::create([
            'organization_id' => $organization->id,
            'user_id' => auth()->id(),
            'reference' => 'SL-'.strtoupper(Str::random(10)),
            'package' => $package,
            'plan' => $packageData['plan'],
            'amount' => $packageData['price'],
            'currency' => config('sendlock.billing.currency'),
            'billing_cycle' => 'monthly',
            'payment_method' => $request->payment_method,
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        // Grant entitlements and lift the billing gate. Monthly billing → the
        // subscription renews/expires one month out.
        $organization->update([
            'subscription_plan' => $packageData['plan'],
            'subscription_status' => 'active',
            'subscribed_at' => Carbon::now(),
            'subscription_expires_at' => Carbon::now()->addMonth(),
        ]);

        AuditLogger::log(
            'SUBSCRIPTION_PAID',
            'PAYMENT',
            $payment->id,
            sprintf(
                'Subscribed to %s (%s%s/%s) via %s — ref %s',
                $packageData['name'],
                $symbol,
                $packageData['price'],
                $packageData['period'],
                $methods[$request->payment_method]['name'],
                $payment->reference
            )
        );

        return redirect()
            ->route('dashboard')
            ->with('success', "Payment successful — welcome to SendLock {$packageData['name']}. Receipt {$payment->reference}.");
    }

    /**
     * Resolve a configured package by key or 404.
     */
    protected function resolvePackage(string $package): array
    {
        $data = config("sendlock.billing.packages.{$package}");

        abort_if(! $data, 404);

        return $data;
    }

    /**
     * A package with no price is the free tier.
     */
    protected function isFree(array $package): bool
    {
        return (float) $package['price'] === 0.0;
    }

    /**
     * The configured package whose `plan` matches the given plan key, or null.
     */
    protected function packageForPlan(?string $plan): ?array
    {
        $plan = strtolower((string) $plan);

        foreach ((array) config('sendlock.billing.packages', []) as $package) {
            if (($package['plan'] ?? null) === $plan) {
                return $package;
            }
        }

        return null;
    }
}
