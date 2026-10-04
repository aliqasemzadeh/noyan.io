<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? __('general.app_name') }}</title>

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
                </div>
            </div>
        </div>

        @livewireScripts
        @fluxScripts
    </body>
</html>
