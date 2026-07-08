@props(['user'])

{{-- Admin-only reveal of a user's system-issued temporary password. Renders
     nothing once the user has set their own password. --}}
@if($user->hasPendingTemporaryPassword())
    <div x-data="{ show: false, copied: false, pw: @js($user->temporary_password) }"
         {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-2 py-1']) }}>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-amber-600">Temp</span>
        <code class="font-mono text-xs text-amber-900" x-text="show ? pw : '••••••••••'"></code>
        <button type="button" @click="show = !show"
                class="text-[11px] font-medium text-amber-700 hover:text-amber-900"
                x-text="show ? 'Hide' : 'Show'"></button>
        <button type="button"
                @click="navigator.clipboard.writeText(pw); copied = true; setTimeout(() => copied = false, 1500)"
                class="text-[11px] font-medium text-amber-700 hover:text-amber-900">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak>Copied</span>
        </button>
    </div>
@endif
