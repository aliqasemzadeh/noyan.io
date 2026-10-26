<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @php
            try {
                $siteFavicon = app(\App\Settings\GeneralSettings::class)->site_favicon;
            } catch (Throwable) {
                $siteFavicon = null;
            }
        @endphp
        @if (filled($siteFavicon))
            <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($siteFavicon) }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance
    </head>
    <body class="min-h-dvh overflow-x-hidden bg-zinc-50 dark:bg-zinc-900 antialiased">
        @php
            $currentBusiness = auth()->user()?->currentBusiness;
            $brandName = $currentBusiness?->name ?? __('general.app_name');
            $brandLogo = $currentBusiness?->logoUrl();
            $authUser = auth()->user();
            $showAiComposer = $authUser !== null && (
                (request()->routeIs('system.users.*') && $authUser->can('user_view'))
                || (request()->routeIs('accounting.*') && $authUser->canAccessPanel(\App\Enums\Business\BusinessPanel::Accounting))
            );
            $aiComposerContext = request()->routeIs('system.users.*') ? 'users' : 'accounting';
            $aiAssistantTitle = $aiComposerContext === 'users'
                ? __('general.users')
                : __('general.accounting_ai_title');
        @endphp

        <flux:sidebar
            sticky
            collapsible
            class="bg-zinc-50 dark:bg-zinc-900 max-lg:border-e max-lg:border-zinc-200 dark:max-lg:border-zinc-700 data-flux-sidebar-on-mobile:data-flux-sidebar-collapsed-mobile:max-lg:hidden!"
        >
            <flux:sidebar.header>
                <flux:sidebar.brand
                    :href="route('dashboard')"
                    wire:navigate
                    :name="$brandName"
                    :logo="$brandLogo"
                    :alt="$brandName"
                />

                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <livewire:layout.app.sidebar :key="'layout-app-sidebar'" />

            <flux:sidebar.spacer />

            <livewire:layout.app.panels :key="'layout-app-panels'" />

            <div class="w-full in-data-flux-sidebar-collapsed-desktop:hidden">
                <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance" class="w-full">
                    <flux:radio value="light" icon="sun" icon:variant="outline" class="px-2! [&_svg]:size-4!" />
                    <flux:radio value="dark" icon="moon" icon:variant="outline" class="px-2! [&_svg]:size-4!" />
                    <flux:radio value="system" icon="computer-desktop" icon:variant="outline" class="px-2! [&_svg]:size-4!" />
                </flux:radio.group>
            </div>

            <livewire:layout.app-shell variant="sidebar" :key="'layout-app-shell-sidebar'" />
        </flux:sidebar>

        <flux:header class="lg:hidden flex items-center justify-between gap-2 border-b border-zinc-200 bg-white px-4! dark:border-zinc-700 dark:bg-zinc-800">
            <flux:sidebar.toggle class="lg:hidden shrink-0" icon="bars-2" inset="left" />

            <livewire:layout.app-shell variant="mobile-brand" :key="'layout-app-shell-mobile-brand'" />
        </flux:header>

        <flux:main inset class="lg:ms-0">
            <livewire:layout.impersonation-banner :key="'layout-impersonation-banner'" />

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
                <div class="flex h-[100dvh] min-h-0 flex-col bg-zinc-50 dark:bg-zinc-950">
                    <div class="shrink-0 border-b border-zinc-200/80 bg-white/90 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90">
                        <div class="mx-auto flex max-w-3xl items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-500/15 text-teal-600 ring-1 ring-teal-500/20 dark:text-teal-300">
                                <flux:icon.sparkles class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <flux:heading size="lg">{{ $aiAssistantTitle }}</flux:heading>
                                <flux:text class="mt-0.5 text-sm">{{ __('general.ai_assistant_modal_hint') }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="mx-auto min-h-0 w-full max-w-3xl flex-1 overflow-hidden px-4 py-4">
                        <livewire:ai.composer
                            :context="$aiComposerContext"
                            :key="'ai-composer-'.$aiComposerContext"
                        />
                    </div>
                </div>
            </flux:modal>
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScripts
        @fluxScripts
    </body>
</html>
