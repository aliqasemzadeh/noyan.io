<?php

use App\Models\Accounting\Invoice;
use App\Models\Business;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new #[Layout('layouts::invoice')] class extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        abort_unless($invoice->isFinalized(), 404);

        $this->invoice = $invoice->loadMissing(['items', 'party', 'business.media']);
    }

    public function business(): Business
    {
        return $this->invoice->business;
    }

    public function formatDate(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Jalalian::fromDateTime($date)->format('Y/m/d');
    }

    public function formatAmount(string $amount): string
    {
        return number_format((float) $amount, 0, '.', ',');
    }
};
?>

@php
    $business = $this->business();
    $logoUrl = $business->logoUrl();
    $primary = $business->invoicePrimaryColor();
    $secondary = $business->invoiceSecondaryColor();
    $initial = mb_substr($business->name, 0, 1);
@endphp

<x-slot name="title">{{ __('general.invoice') }} {{ $invoice->invoice_number }} - {{ $business->name }}</x-slot>

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <div class="no-print mb-4 flex justify-end">
        <flux:button
            type="button"
            variant="primary"
            color="teal"
            icon="printer"
            x-on:click="window.print()"
        >
            {{ __('general.print_invoice') }}
        </flux:button>
    </div>

    <div class="invoice-sheet overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div
            class="px-6 py-5 text-white sm:px-8"
            style="background: linear-gradient(135deg, {{ $primary }}, {{ $secondary }});"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    @if ($logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ $business->name }}"
                            class="size-16 rounded-xl bg-white/95 object-contain p-1.5 shadow-sm"
                        />
                    @else
                        <div class="flex size-16 items-center justify-center rounded-xl bg-white/20 text-2xl font-bold backdrop-blur">
                            {{ $initial }}
                        </div>
                    @endif

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ $business->name }}</h1>
                        @if (filled($business->phone) || filled($business->address))
                            <div class="mt-2 space-y-1 text-sm text-white/90">
                                @if (filled($business->phone))
                                    <p dir="ltr">{{ $business->phone }}</p>
                                @endif
                                @if (filled($business->address))
                                    <p>{{ $business->address }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-end">
                    <p class="text-sm text-white/80">{{ __('general.invoice') }}</p>
                    <p class="mt-1 text-xl font-semibold" dir="ltr">{{ $invoice->invoice_number }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6 px-6 py-6 sm:px-8">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('general.party') }}</p>
                    <p class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->displayPartyName() ?: '—' }}</p>
                </div>
                <div class="sm:text-end">
                    <p class="text-sm text-zinc-500">{{ __('general.issue_date') }}</p>
                    <p class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatDate($invoice->issue_date) }}</p>
                    @if ($invoice->due_date)
                        <p class="mt-2 text-sm text-zinc-500">{{ __('general.due_date') }}</p>
                        <p class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatDate($invoice->due_date) }}</p>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl ring-1 ring-zinc-200 dark:ring-zinc-700">
                <table class="min-w-full text-sm">
                    <thead style="background-color: color-mix(in srgb, {{ $primary }} 12%, transparent);">
                        <tr class="text-start">
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-200">#</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-200">{{ __('general.title') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-200">{{ __('general.quantity') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-200">{{ __('general.unit_price') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-200">{{ __('general.row_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($invoice->items as $index => $item)
                            <tr>
                                <td class="px-4 py-3 text-zinc-500">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $item->title }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ $this->formatAmount((string) $item->quantity) }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ $this->formatAmount((string) $item->unit_price) }}</td>
                                <td class="px-4 py-3 font-medium" dir="ltr">{{ $this->formatAmount((string) $item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ms-auto max-w-xs space-y-2 text-sm">
                <div class="flex justify-between gap-6">
                    <span class="text-zinc-500">{{ __('general.items_total') }}</span>
                    <span dir="ltr">{{ $this->formatAmount((string) $invoice->items_total) }}</span>
                </div>
                @if ((float) $invoice->global_discount > 0)
                    <div class="flex justify-between gap-6">
                        <span class="text-zinc-500">{{ __('general.global_discount') }}</span>
                        <span dir="ltr">{{ $this->formatAmount((string) $invoice->global_discount) }}</span>
                    </div>
                @endif
                @if ((float) $invoice->global_tax > 0)
                    <div class="flex justify-between gap-6">
                        <span class="text-zinc-500">{{ __('general.global_tax') }}</span>
                        <span dir="ltr">{{ $this->formatAmount((string) $invoice->global_tax) }}</span>
                    </div>
                @endif
                <div
                    class="flex justify-between gap-6 border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700"
                    style="color: {{ $primary }};"
                >
                    <span>{{ __('general.total_amount') }}</span>
                    <span dir="ltr">{{ $this->formatAmount((string) $invoice->total_amount) }}</span>
                </div>
            </div>

            @if (filled($invoice->note))
                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                    <p class="text-sm text-zinc-500">{{ __('general.note') }}</p>
                    <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $invoice->note }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
