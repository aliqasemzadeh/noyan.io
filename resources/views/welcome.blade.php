<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        @php
            use App\Settings\GeneralSettings;
            use Illuminate\Support\Facades\Storage;

            try {
                $settings = app(GeneralSettings::class);
                $siteName = filled($settings->site_name) ? $settings->site_name : __('general.app_name');
                $siteShortName = filled($settings->site_short_name) ? $settings->site_short_name : $siteName;
                $siteDescription = filled($settings->site_description) ? $settings->site_description : __('general.welcome_subheadline');
                $siteLogoUrl = filled($settings->site_logo) ? Storage::disk('public')->url($settings->site_logo) : null;
                $siteFaviconUrl = filled($settings->site_favicon) ? Storage::disk('public')->url($settings->site_favicon) : null;
            } catch (Throwable) {
                $siteName = __('general.app_name');
                $siteShortName = $siteName;
                $siteDescription = __('general.welcome_subheadline');
                $siteLogoUrl = null;
                $siteFaviconUrl = null;
            }

            $ctaUrl = auth()->check()
                ? route('dashboard')
                : (Route::has('login') ? route('login') : url('/'));
        @endphp

        <title>{{ $siteName }}</title>
        <meta name="description" content="{{ $siteDescription }}">

        @if (filled($siteFaviconUrl))
            <link rel="icon" href="{{ $siteFaviconUrl }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="relative flex min-h-screen flex-col">
            {{-- Atmospheric background --}}
            <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-br from-zinc-50 via-sky-50/40 to-zinc-100 dark:from-zinc-950 dark:via-[#071F43] dark:to-zinc-900"></div>
                <div
                    class="absolute inset-0 opacity-[0.35] dark:opacity-[0.2]"
                    style="background-image: linear-gradient(to right, rgb(22 113 157 / 0.08) 1px, transparent 1px), linear-gradient(to bottom, rgb(22 113 157 / 0.08) 1px, transparent 1px); background-size: 3.5rem 3.5rem;"
                ></div>
                <div class="absolute start-1/2 top-[38%] h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#16719D]/15 blur-3xl dark:bg-[#2DE1C2]/10"></div>
                <div class="absolute end-0 top-0 h-64 w-64 rounded-full bg-[#16719D]/10 blur-3xl dark:bg-[#16719D]/5"></div>
            </div>

            {{-- Header --}}
            <header class="relative z-10 border-b border-zinc-200/70 bg-white/75 backdrop-blur-md dark:border-zinc-800/70 dark:bg-zinc-950/75">
                <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 sm:px-6">
                    <flux:brand
                        href="{{ url('/') }}"
                        :name="$siteName"
                        :logo="$siteLogoUrl"
                        :alt="$siteName"
                        class="!me-0 shrink-0"
                    />

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

                        <div x-data>
                            <flux:tooltip content="{{ __('general.toggle_theme') }}">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    class="relative"
                                    x-on:click="$flux.dark = ! $flux.dark"
                                    x-bind:aria-label="$flux.dark ? '{{ __('general.theme_light') }}' : '{{ __('general.theme_dark') }}'"
                                >
                                    <span class="relative block size-5">
                                        <flux:icon.sun class="absolute inset-0 size-5 opacity-100 transition-opacity duration-300 dark:opacity-0" />
                                        <flux:icon.moon class="absolute inset-0 size-5 opacity-0 transition-opacity duration-300 dark:opacity-100" />
                                    </span>
                                </flux:button>
                            </flux:tooltip>
                        </div>

                        @if (Route::has('login'))
                            @auth
                                <flux:button size="sm" variant="primary" :href="route('dashboard')">
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
                    <div class="flex items-center gap-1 sm:hidden" x-data>
                        <flux:tooltip content="{{ __('general.toggle_theme') }}">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                class="relative"
                                x-on:click="$flux.dark = ! $flux.dark"
                                x-bind:aria-label="$flux.dark ? '{{ __('general.theme_light') }}' : '{{ __('general.theme_dark') }}'"
                            >
                                <span class="relative block size-5">
                                    <flux:icon.sun class="absolute inset-0 size-5 opacity-100 transition-opacity duration-300 dark:opacity-0" />
                                    <flux:icon.moon class="absolute inset-0 size-5 opacity-0 transition-opacity duration-300 dark:opacity-100" />
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
                        @if (filled($siteLogoUrl))
                            <img
                                src="{{ $siteLogoUrl }}"
                                alt="{{ $siteName }}"
                                class="mx-auto mb-6 h-14 w-auto motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out"
                            />
                        @endif

                        <p
                            class="text-sm font-medium tracking-wide text-[#16719D] dark:text-[#2DE1C2] motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out"
                        >
                            {{ $siteShortName }}
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
                            {{ $siteDescription }}
                        </flux:text>

                        <div class="mt-10 motion-safe:translate-y-4 motion-safe:opacity-0 motion-safe:delay-300 motion-safe:starting:translate-y-4 motion-safe:starting:opacity-0 motion-safe:opacity-100 motion-safe:translate-y-0 motion-safe:transition motion-safe:duration-700 motion-safe:ease-out">
                            <flux:button
                                variant="primary"
                                :href="$ctaUrl"
                                class="w-full transition duration-300 motion-safe:hover:-translate-y-0.5 motion-safe:hover:shadow-lg motion-safe:hover:shadow-[#16719D]/25 sm:w-auto sm:min-w-[12rem]"
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
                                <flux:icon.document-text class="size-6 text-[#16719D] dark:text-[#2DE1C2]" />
                            </div>
                            <flux:heading size="lg">{{ __('general.welcome_feature_invoices_title') }}</flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('general.welcome_feature_invoices_body') }}
                            </flux:text>
                        </div>

                        <div class="space-y-3 text-center md:text-start">
                            <div class="flex justify-center md:justify-start">
                                <flux:icon.banknotes class="size-6 text-[#16719D] dark:text-[#2DE1C2]" />
                            </div>
                            <flux:heading size="lg">{{ __('general.welcome_feature_ledger_title') }}</flux:heading>
                            <flux:text class="text-zinc-600 dark:text-zinc-400">
                                {{ __('general.welcome_feature_ledger_body') }}
                            </flux:text>
                        </div>

                        <div class="space-y-3 text-center md:text-start">
                            <div class="flex justify-center md:justify-start">
                                <flux:icon.building-office-2 class="size-6 text-[#16719D] dark:text-[#2DE1C2]" />
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
                    &copy; {{ date('Y') }} {{ $siteName }}
                </flux:text>
                <flux:text class="mt-2 block font-mono text-xs text-zinc-400 dark:text-zinc-600" dir="ltr">
                    {{ __('general.app_version_label', ['version' => \App\Support\System\AppVersion::current()]) }}
                </flux:text>
            </footer>
        </div>

        @livewireScripts
        @fluxScripts
    </body>
</html>
