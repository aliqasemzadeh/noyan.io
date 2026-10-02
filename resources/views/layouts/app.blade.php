<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
        <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.header>
                <flux:sidebar.brand
                    :href="route('system.dashboard')"
                    wire:navigate
                    :name="config('app.name')"
                />

                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:layout.app.sidebar :key="'layout-app-sidebar'" />

            <flux:sidebar.spacer />

            <livewire:layout.app.panels :key="'layout-app-panels'" />

            <livewire:layout.app-shell variant="sidebar" :key="'layout-app-shell-sidebar'" />
        </flux:sidebar>

        <flux:header class="block! bg-white lg:bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
            <flux:navbar class="lg:hidden w-full">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <flux:spacer />

                <livewire:layout.app-shell variant="header" :key="'layout-app-shell-header'" />
            </flux:navbar>

            <flux:navbar scrollable>
                @if (request()->routeIs('system.*'))
                    <flux:navbar.item
                        :href="route('system.dashboard')"
                        wire:navigate
                        :current="request()->routeIs('system.dashboard')"
                    >
                        {{ __('general.dashboard') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        :href="route('system.users.index')"
                        wire:navigate
                        :current="request()->routeIs('system.users.*')"
                    >
                        {{ __('general.users') }}
                    </flux:navbar.item>

                    <flux:navbar.item
                        :href="route('system.businesses.index')"
                        wire:navigate
                        :current="request()->routeIs('system.businesses.*')"
                    >
                        {{ __('general.businesses') }}
                    </flux:navbar.item>
                @elseif (request()->routeIs('user.*'))
                    <flux:navbar.item
                        :href="route('user.dashboard')"
                        wire:navigate
                        :current="request()->routeIs('user.dashboard')"
                    >
                        {{ __('general.dashboard') }}
                    </flux:navbar.item>
                @elseif (request()->routeIs('accounting.*'))
                    <flux:navbar.item
                        :href="route('accounting.dashboard')"
                        wire:navigate
                        :current="request()->routeIs('accounting.dashboard')"
                    >
                        {{ __('general.dashboard') }}
                    </flux:navbar.item>
                @endif
            </flux:navbar>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @livewireScripts
        @fluxScripts
    </body>
</html>
