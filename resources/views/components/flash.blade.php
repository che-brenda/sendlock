{{--
    Global flash notifications (toasts). Surfaces every stage outcome — success,
    error, warning, info, Breeze auth statuses, and validation errors — as a
    dismissible, auto-fading pop-up. Included once in the layout; controllers just
    flash ->with('success'|'error'|'warning'|'info', ...) or ->withErrors(...).
--}}
@php
    $flash = [];

    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (session($type)) {
            $flash[] = ['type' => $type, 'text' => session($type)];
        }
    }

    // Breeze auth statuses -> friendly text.
    $statusMap = [
        'profile-updated' => 'Your profile has been updated.',
        'password-updated' => 'Your password has been updated.',
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
    ];
    if ($status = session('status')) {
        $flash[] = ['type' => 'success', 'text' => $statusMap[$status] ?? $status];
    }

    if ($errors->any()) {
        $flash[] = [
            'type' => 'error',
            'text' => $errors->count() === 1
                ? $errors->first()
                : $errors->count().' issues need your attention — '.$errors->first(),
        ];
    }

    $styles = [
        'success' => ['accent' => 'bg-emerald-500', 'icon' => 'text-emerald-500', 'path' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'error' => ['accent' => 'bg-rose-500', 'icon' => 'text-rose-500', 'path' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z'],
        'warning' => ['accent' => 'bg-amber-500', 'icon' => 'text-amber-500', 'path' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
        'info' => ['accent' => 'bg-sky-500', 'icon' => 'text-sky-500', 'path' => 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z'],
    ];
@endphp

@if(count($flash))
<div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-3 px-4 sm:items-end sm:px-6" aria-live="polite">
    @foreach($flash as $msg)
    @php $s = $styles[$msg['type']]; @endphp
    <div x-data="{ show: true }"
         x-init="setTimeout(() => show = false, 6500)"
         x-show="show"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-x-0"
         x-transition:leave-end="opacity-0 translate-x-4"
         role="status"
         class="pointer-events-auto flex w-full max-w-sm items-start gap-3 overflow-hidden rounded-xl border border-slate-200 bg-white pr-3 shadow-lg">
        <div class="{{ $s['accent'] }} w-1.5 self-stretch"></div>
        <svg class="mt-3 h-5 w-5 shrink-0 {{ $s['icon'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['path'] }}" />
        </svg>
        <p class="py-3 text-sm text-slate-700">{{ $msg['text'] }}</p>
        <button type="button" @click="show = false" class="mt-3 ml-auto shrink-0 text-slate-300 transition hover:text-slate-500" aria-label="Dismiss">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </div>
    @endforeach
</div>
@endif
