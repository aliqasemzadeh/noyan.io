<?php

use App\Enums\Business\InvoiceOrientation;
use App\Models\Accounting\Invoice;
use App\Models\Business;
use App\Support\InvoiceDocumentFooter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new #[Layout('layouts::invoice')] class extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        abort_unless($invoice->isFinalized(), 404);

        $this->invoice = $invoice->loadMissing([
            'items',
            'party',
            'accounts',
            'business.media',
            'business.invoiceSettings',
        ]);
    }

    public function business(): Business
    {
        return $this->invoice->business;
    }

    public function documentFooter(): ?\Illuminate\Support\HtmlString
    {
        return InvoiceDocumentFooter::forBusiness($this->business());
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

    public function orientation(): InvoiceOrientation
    {
        return $this->business()->invoiceOrientation();
    }
};
?>

@php
    $business = $this->business();
    $orientation = $this->orientation();
    $containerClass = $orientation === InvoiceOrientation::Landscape
        ? 'mx-auto max-w-5xl px-4 py-8 sm:px-6'
        : 'mx-auto max-w-3xl px-4 py-8 sm:px-6';
    $dates = [
        ['label' => __('general.issue_date'), 'value' => $this->formatDate($invoice->issue_date)],
    ];

    if ($invoice->due_date) {
        $dates[] = ['label' => __('general.due_date'), 'value' => $this->formatDate($invoice->due_date)];
    }
@endphp

<x-slot name="title">{{ __('general.invoice') }} {{ $invoice->invoice_number }} - {{ $business->name }}</x-slot>
<x-slot name="invoiceOrientation">{{ $orientation->value }}</x-slot>

<div class="{{ $containerClass }}">
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
        :accounts="$invoice->accounts"
        :format-amount="fn (string $amount): string => $this->formatAmount($amount)"
    >
        @if ($footer = $this->documentFooter())
            <x-slot:footer>
                {{ $footer }}
            </x-slot:footer>
        @endif
    </x-accounting.printable-document>
</div>
