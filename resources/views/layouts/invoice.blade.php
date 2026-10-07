<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? __('general.invoice') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance

        <style>
            @media print {
                .no-print {
                    display: none !important;
                }

                body {
                    background: white !important;
                }

                .invoice-sheet {
                    box-shadow: none !important;
                    border: none !important;
                    margin: 0 !important;
                    max-width: none !important;
                }
            }
        </style>
    </head>
    <body class="min-h-dvh bg-zinc-100 antialiased dark:bg-zinc-950">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
