@php
    use App\Enums\Business\InvoiceOrientation;

    $orientation = $journalOrientation ?? InvoiceOrientation::Portrait;
    if (! $orientation instanceof InvoiceOrientation) {
        $orientation = InvoiceOrientation::tryFrom((string) $orientation) ?? InvoiceOrientation::Portrait;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\LocaleDate::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? __('general.journal_entry') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @fluxAppearance

        <style>
            .journal-sheet {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            @media print {
                @page {
                    size: {{ $orientation->pageSize() }};
                    margin: 8mm;
                }

                .no-print {
                    display: none !important;
                }

                html,
                body {
                    background: white !important;
                    color: #000 !important;
                }

                .journal-sheet {
                    box-shadow: none !important;
                    margin: 0 !important;
                    max-width: none !important;
                    break-inside: avoid;
                    page-break-inside: avoid;
                }

                .journal-doc {
                    font-size: 9pt !important;
                    line-height: 1.25 !important;
                }

                .journal-doc table {
                    font-size: 8pt !important;
                }
            }
        </style>
    </head>
    <body class="min-h-dvh overflow-x-hidden bg-zinc-100 antialiased dark:bg-zinc-950">
        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
