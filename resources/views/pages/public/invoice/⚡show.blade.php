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
    $dates = [
        ['label' => __('general.issue_date'), 'value' => $this->formatDate($invoice->issue_date)],
    ];

    if ($invoice->due_date) {
        $dates[] = ['label' => __('general.due_date'), 'value' => $this->formatDate($invoice->due_date)];
    }
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

    <x-accounting.printable-document
        :business="$business"
        :document-label="__('general.invoice')"
        :number="$invoice->invoice_number"
        :party-name="$invoice->displayPartyName()"
        :dates="$dates"
        :items="$invoice->items"
        :items-total="$invoice->items_total"
        :global-discount="$invoice->global_discount"
        :global-tax="$invoice->global_tax"
        :total-amount="$invoice->total_amount"
        :note="$invoice->note"
        :format-amount="fn (string $amount): string => $this->formatAmount($amount)"
    />
</div>
