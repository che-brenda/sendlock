<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SendLock') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-slate-50">

            <!-- Mobile backdrop -->
            <div x-show="sidebarOpen" x-cloak
                 @click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

            <!-- Global flash notifications (success / error / info pop-ups) -->
            <x-flash />

            <!-- Sidebar -->
            @include('layouts.navigation')

            <!-- Main column -->
            <div class="lg:pl-64">
                <!-- Top bar -->
                <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8">
                    <button @click="sidebarOpen = true" class="text-slate-500 lg:hidden">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </div>

                    <!-- User dropdown -->
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-600 text-xs font-semibold text-white">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block font-medium text-slate-800">{{ Auth::user()->name }}</span>
                                    <span class="block text-xs text-slate-400">{{ Auth::user()->getRoleNames()->first() ?? 'Member' }}</span>
                                </span>
                                <svg class="h-4 w-4 fill-current text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <!-- Page content -->
                <main class="min-h-[calc(100vh-4rem)]">
                    {{ $slot }}
                </main>

                <!-- App footer -->
                <footer class="border-t border-slate-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center justify-between gap-2 text-xs text-slate-400 sm:flex-row">
                        <p>&copy; {{ date('Y') }} SendLock Security · Business Communication Trust Platform</p>
                        <p>{{ Auth::user()->organization?->organization_name ?? 'SendLock Platform' }}</p>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
