<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? __('general.app_name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 dark:bg-zinc-900 antialiased">
        <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900">
            <flux:sidebar.header>
                <flux:sidebar.brand
                    :href="route('dashboard')"
                    wire:navigate
                    :name="__('general.app_name')"
                />

                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <livewire:layout.app.sidebar :key="'layout-app-sidebar'" />

            <flux:sidebar.spacer />

            <livewire:layout.app.panels :key="'layout-app-panels'" />

            <livewire:layout.app-shell variant="sidebar" :key="'layout-app-shell-sidebar'" />
        </flux:sidebar>

        <flux:header class="lg:hidden bg-white dark:bg-zinc-800">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:layout.app-shell variant="header" :key="'layout-app-shell-header'" />
        </flux:header>

        <flux:main inset class="lg:ms-0">
            {{ $slot }}

            @if (request()->routeIs('accounting.*'))
                <div class="sticky bottom-0 z-20 -mx-6 mt-6 border-t border-zinc-200 bg-white/95 px-6 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
                    <div class="mx-auto max-w-3xl">
                        <livewire:ai.composer context="accounting" :key="'ai-composer-accounting'" />
                    </div>
                </div>
            @endif
        </flux:main>

        @livewireScripts
        @fluxScripts
    </body>
</html>
