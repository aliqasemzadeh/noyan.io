<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
    <body>
        <div class="flex min-h-screen">
            <div class="flex flex-1 items-center justify-center px-4 py-12">
                <div class="w-full max-w-80 space-y-6">
                    {{ $slot }}
                </div>
            </div>

            <div class="hidden flex-1 p-4 lg:flex">
                <div class="relative flex h-full w-full flex-col items-start justify-end rounded-lg bg-gradient-to-br from-zinc-900 via-teal-950 to-zinc-900 p-16 text-white">
                    <div class="text-3xl font-semibold xl:text-4xl">{{ __('general.app_name') }}</div>
                    <flux:text class="mt-3 text-zinc-300">{{ __('general.login_panel_tagline') }}</flux:text>
                    <flux:text class="mt-6 font-mono text-xs text-zinc-400" dir="ltr">
                        {{ __('general.app_version_label', ['version' => \App\Support\System\AppVersion::current()]) }}
                    </flux:text>
                </div>
            </div>
        </div>

        <div class="pointer-events-none fixed inset-x-0 bottom-3 z-10 flex justify-center lg:hidden">
            <span class="rounded-full bg-zinc-900/70 px-3 py-1 font-mono text-[11px] text-zinc-300 backdrop-blur" dir="ltr">
                {{ __('general.app_version_label', ['version' => \App\Support\System\AppVersion::current()]) }}
            </span>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScripts
        @fluxScripts
    </body>
</html>
