@props([
    'label',                                   // the initial button text
    'message' => 'Are you sure?',              // the confirmation question
    'confirm' => 'Yes',                        // the confirm (submit) button text
    'class' => 'bg-teal-600 text-white hover:bg-teal-700',
])

{{--
    A reusable two-step confirmation for a consequential action. Place it INSIDE a
    <form>; clicking the button reveals an inline "message · No · Yes" prompt, and
    only the Yes button submits the form. This is the standard way to ask for
    confirmation before any state-changing action in the app.
--}}
<span x-data="{ confirming: false }" class="inline-flex items-center gap-2">
    <button type="button" x-show="!confirming" @click="confirming = true"
            {{ $attributes->merge(['class' => 'rounded-lg px-4 py-2 text-sm font-medium '.$class]) }}>{{ $label }}</button>

    <span x-show="confirming" x-cloak class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1">
        <span class="text-xs font-medium text-slate-600">{{ $message }}</span>
        <button type="button" @click="confirming = false"
                class="rounded-md px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100">No</button>
        <button type="submit"
                class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $class }}">{{ $confirm }}</button>
    </span>
</span>
