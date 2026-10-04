<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ __('general.app_name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        @php
            $ctaUrl = auth()->check()
                ? route('dashboard')
                : (Route::has('login') ? route('login') : url('/'));
        @endphp

        <div class="relative flex min-h-screen flex-col">
            {{-- Atmospheric background --}}
            <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-br from-zinc-50 via-teal-50/40 to-zinc-100 dark:from-zinc-950 dark:via-teal-950/80 dark:to-zinc-900"></div>
                <div
                    class="absolute inset-0 opacity-[0.35] dark:opacity-[0.2]"
                    style="background-image: linear-gradient(to right, rgb(13 148 136 / 0.08) 1px, transparent 1px), linear-gradient(to bottom, rgb(13 148 136 / 0.08) 1px, transparent 1px); background-size: 3.5rem 3.5rem;"
                ></div>
                <div class="absolute start-1/2 top-[38%] h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-teal-400/15 blur-3xl dark:bg-teal-500/10"></div>
                <div class="absolute end-0 top-0 h-64 w-64 rounded-full bg-teal-600/10 blur-3xl dark:bg-teal-400/5"></div>
            </div>

            {{-- Header --}}
            <header class="relative z-10 border-b border-zinc-200/70 bg-white/75 backdrop-blur-md dark:border-zinc-800/70 dark:bg-zinc-950/75">
                <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 sm:px-6">
                    <flux:brand href="{{ url('/') }}" :name="__('general.app_name')" class="!me-0 shrink-0" />

                    <flux:spacer />

                    {{-- Desktop actions --}}
                    <flux:navbar class="!hidden !py-0 sm:!flex sm:items-center sm:gap-1">
                        <flux:dropdown>
                            <flux:button size="sm" variant="ghost" icon="language" icon:trailing="chevron-down">
                                {{ __('general.language') }}
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item :href="route('locale.switch', 'fa')">
                                    {{ __('general.language_fa') }}
                                </flux:menu.item>
                                <flux:menu.item :href="route('locale.switch', 'en')">
                                    {{ __('general.language_en') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        <div
                            x-data="{
                                dark: document.documentElement.classList.contains('dark'),
                                toggle() {
                                    this.dark = ! this.dark;
                                    window.Flux.applyAppearance(this.dark ? 'dark' : 'light');
                                },
                            }"
                            x-init="
                                dark = document.documentElement.classList.contains('dark');
                                new MutationObserver(() => { dark = document.documentElement.classList.contains('dark') }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                            "
                        >
                            <flux:tooltip content="{{ __('general.toggle_theme') }}">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    class="relative"
                                    x-on:click="toggle()"
                                    x-bind:aria-label="dark ? '{{ __('general.theme_light') }}' : '{{ __('general.theme_dark') }}'"
                                >
                                    <span class="relative block size-5">
                                        <flux:icon.sun
                                            class="absolute inset-0 size-5 transition-opacity duration-300"
                                            x-bind:class="dark ? 'opacity-0' : 'opacity-100'"
                                        />
                                        <flux:icon.moon
                                            class="absolute inset-0 size-5 transition-opacity duration-300"
                                            x-bind:class="dark ? 'opacity-100' : 'opacity-0'"
                                        />
                                    </span>
                                </flux:button>
                            </flux:tooltip>
                        </div>

                        @if (Route::has('login'))
                            @auth
                                <flux:button size="sm" variant="primary" color="teal" :href="route('dashboard')">
                                    {{ __('general.dashboard') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" :href="route('login')">
                                    {{ __('general.login') }}
                                </flux:button>
                            @endauth
                        @endif
                    </flux:navbar>

                    {{-- Mobile: theme + overflow menu --}}
                    <div
                        class="flex items-center gap-1 sm:hidden"
                        x-data="{
                            dark: document.documentElement.classList.contains('dark'),
                            toggle() {
                                this.dark = ! this.dark;
                                window.Flux.applyAppearance(this.dark ? 'dark' : 'light');
                            },
                        }"
                        x-init="
                            dark = document.documentElement.classList.contains('dark');
                            new MutationObserver(() => { dark = document.documentElement.classList.contains('dark') }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                        "
                    >
                        <flux:tooltip content="{{ __('general.toggle_theme') }}">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                class="relative"
                                x-on:click="toggle()"
                                x-bind:aria-label="dark ? '{{ __('general.theme_light') }}' : '{{ __('general.theme_dark') }}'"
                            >
                                <span class="relative block size-5">
                                    <flux:icon.sun
                                        class="absolute inset-0 size-5 transition-opacity duration-300"
                                        x-bind:class="dark ? 'opacity-0' : 'opacity-100'"
                                    />
                                    <flux:icon.moon
                                        class="absolute inset-0 size-5 transition-opacity duration-300"
                                        x-bind:class="dark ? 'opacity-100' : 'opacity-0'"
                                    />
                                </span>
                            </flux:button>
                        </flux:tooltip>

                        <flux:dropdown>
                            <flux:button size="sm" variant="ghost" icon="bars-3" />
                            <flux:menu>
                                <flux:menu.item :href="route('locale.switch', 'fa')">
                                    {{ __('general.language_fa') }}
                                </flux:menu.item>
                                <flux:menu.item :href="route('locale.switch', 'en')">
                                    {{ __('general.language_en') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                @if (Route::has('login'))
                                    @auth
                                        <flux:menu.item :href="route('dashboard')">
                                            {{ __('general.dashboard') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item :href="route('login')">
                                            {{ __('general.login') }}
                                        </flux:menu.item>
                                    @endauth
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            </header>

            <main class="relative z-10 flex flex-1 flex-col">
                {{-- Hero --}}
                <section class="flex flex-1 items-center justify-center px-4 py-16 sm:px-6 sm:py-20">
                    <div class="mx-auto w-full max-w-2xl text-center">
                        <p
                            class="text-sm font-medium tracking-wide text-teal-700 dark:text-teal-400 motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out"
                        >
                            {{ __('general.app_name') }}
                        </p>

                        <flux:heading
                            level="1"
                            size="2xl"
                            class="mt-4 text-balance !text-4xl font-semibold tracking-tight text-zinc-900 sm:!text-5xl dark:text-zinc-50 motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:delay-100 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out"
                        >
                            {{ __('general.welcome_headline') }}
                        </flux:heading>

                        <flux:text
                            class="mx-auto mt-5 max-w-xl text-pretty text-base text-zinc-600 dark:text-zinc-400 motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:delay-200 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out"
                        >
                            {{ __('general.welcome_subheadline') }}
                        </flux:text>

                        <div class="mt-10 motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:delay-300 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out">
                            <flux:button
                                variant="primary"
                                color="teal"
                                :href="$ctaUrl"
                                class="w-full transition duration-300 motion-safe:hover:-translate-y-0.5 motion-safe:hover:shadow-lg motion-safe:hover:shadow-teal-500/25 sm:w-auto sm:min-w-[12rem]"
                            >
                                {{ __('general.welcome_cta_get_started') }}
                            </flux:button>

                            @if (Route::has('login') && ! auth()->check() && Route::has('register'))
                                <div class="mt-4">
                                    <flux:button variant="ghost" size="sm" :href="route('register')">
                                        {{ __('general.register') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- Accounting AI intro --}}
                <section class="border-t border-zinc-200/70 px-4 py-12 sm:px-6 sm:py-16">
                    <div class="mx-auto w-full max-w-3xl">
                        <livewire:accounting.ai-intro :key="'accounting-ai-intro'" />
                    </div>
                </section>

                {{-- Feature pillars --}}
                <section class="border-t border-zinc-200/70 bg-white/40 px-4 py-16 dark:border-zinc-800/70 dark:bg-zinc-950/40 sm:px-6">
                    <div class="mx-auto grid max-w-6xl gap-10 md:grid-cols-3 md:gap-8">
                        <div class="space-y-3 text-center md:text-start">
                            <div class="flex justify-center md:justify-start">
                                <flux:icon.document-text class="size-6 text-teal-600 dark:text-teal-400" />
                            </div>
                            <flux:heading size="lg">{{ __('general.welcome_feature_invoices_title') }}</flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('general.welcome_feature_invoices_body') }}
                            </flux:text>
                        </div>

                        <div class="space-y-3 text-center md:text-start">
                            <div class="flex justify-center md:justify-start">
                                <flux:icon.banknotes class="size-6 text-teal-600 dark:text-teal-400" />
                            </div>
                            <flux:heading size="lg">{{ __('general.welcome_feature_ledger_title') }}</flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('general.welcome_feature_ledger_body') }}
                            </flux:text>
                        </div>

                        <div class="space-y-3 text-center md:text-start">
                            <div class="flex justify-center md:justify-start">
                                <flux:icon.building-office-2 class="size-6 text-teal-600 dark:text-teal-400" />
                            </div>
                            <flux:heading size="lg">{{ __('general.welcome_feature_business_title') }}</flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('general.welcome_feature_business_body') }}
                            </flux:text>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="relative z-10 border-t border-zinc-200/70 px-4 py-6 text-center dark:border-zinc-800/70 sm:px-6">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-500">
                    &copy; {{ date('Y') }} {{ __('general.app_name') }}
                </flux:text>
            </footer>
        </div>

        @livewireScripts
        @fluxScripts
    </body>
</html>
