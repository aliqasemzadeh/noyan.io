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
        @php
            $showAiComposer = auth()->check() && (
                request()->routeIs('system.*')
                || request()->routeIs('user.*')
                || request()->routeIs('accounting.*')
            );
            $aiComposerContext = request()->routeIs('system.users.*') ? 'users' : 'accounting';
            $aiAssistantTitle = $aiComposerContext === 'users'
                ? __('general.users')
                : __('general.accounting_ai_title');
        @endphp

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
        </flux:main>

        @if ($showAiComposer)
            <div class="fixed bottom-6 end-6 z-40">
                <flux:modal.trigger name="ai.assistant">
                    <flux:tooltip content="{{ __('general.ai_assistant_open') }}">
                        <flux:button
                            variant="primary"
                            color="teal"
                            icon="sparkles"
                            class="size-14! rounded-full! shadow-lg shadow-teal-500/30"
                        />
                    </flux:tooltip>
                </flux:modal.trigger>
            </div>

            <flux:modal
                name="ai.assistant"
                class="flex h-dvh max-h-dvh w-full max-w-none! flex-col rounded-none! p-0!"
            >
                <div class="flex h-[100dvh] min-h-0 flex-col">
                    <div class="shrink-0 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <flux:heading size="lg">{{ $aiAssistantTitle }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.ai_assistant_modal_hint') }}</flux:text>
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden px-4 py-4">
                        <livewire:ai.composer
                            :context="$aiComposerContext"
                            :key="'ai-composer-'.$aiComposerContext"
                        />
                    </div>
                </div>
            </flux:modal>
        @endif

        @livewireScripts
        @fluxScripts
    </body>
</html>
