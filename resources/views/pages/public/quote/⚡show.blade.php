<?php

use App\Enums\Business\InvoiceOrientation;
use App\Models\Accounting\Quote;
use App\Models\Business;
use App\Support\InvoiceDocumentFooter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new #[Layout('layouts::invoice')] class extends Component
{
    public Quote $quote;

    public function mount(Quote $quote): void
    {
        abort_unless($quote->isFinalized(), 404);

        $this->quote = $quote->loadMissing([
            'items',
            'party',
            'saleKind',
            'settlementType',
            'business.media',
            'business.invoiceSettings',
        ]);
    }

    public function business(): Business
    {
        return $this->quote->business;
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
        ? 'mx-auto max-w-6xl px-3 py-4 sm:px-4'
        : 'mx-auto max-w-5xl px-3 py-4 sm:px-4';
@endphp

<x-slot name="title">{{ __('general.quote') }} {{ $quote->quote_number }} - {{ $business->name }}</x-slot>
<x-slot name="invoiceOrientation">{{ $orientation->value }}</x-slot>

<div class="{{ $containerClass }}">
    <x-accounting.printable-document
        :business="$business"
        :document-label="__('general.quote')"
        :number="$quote->quote_number"
        :party-name="$quote->displayPartyName()"
        :sale-kind-name="$quote->saleKind?->name"
        :settlement-type-name="$quote->settlementType?->name"
        :buyer-legal-name="$quote->party?->legal_name"
        :buyer-national-id="$quote->party?->national_id"
        :buyer-economic-code="$quote->party?->economic_code"
        :buyer-postal-code="$quote->party?->postal_code"
        :buyer-phone="$quote->party?->mobile ?: $quote->party?->phone"
        :buyer-address="$quote->party?->address"
        :dates="[
            ['label' => __('general.issue_date'), 'value' => $this->formatDate($quote->issue_date)],
            ['label' => __('general.valid_until'), 'value' => $this->formatDate($quote->valid_until)],
        ]"
        :items="$quote->items"
        :items-total="$quote->items_total"
        :global-discount="$quote->global_discount"
        :global-tax="$quote->global_tax"
        :total-amount="$quote->total_amount"
        :note="$quote->note"
        print-storage-key="quote-print-columns"
        :format-amount="fn (string $amount): string => $this->formatAmount($amount)"
    >
        <x-slot:toolbar>
            <flux:button
                type="button"
                variant="primary"
                color="teal"
                icon="printer"
                x-on:click="window.print()"
            >
                {{ __('general.print_quote') }}
            </flux:button>
        </x-slot:toolbar>

        <x-slot:banner>
            @if ($quote->isExpired())
                <flux:callout icon="clock-alert" variant="danger" inline>
                    {{ __('general.quote_expired_notice') }}
                </flux:callout>
            @endif

            @if ($quote->isConverted())
                <flux:callout icon="file-check" variant="secondary" inline class="mt-3">
                    {{ __('general.quote_converted_notice') }}
                </flux:callout>
            @endif
        </x-slot:banner>

        @if ($footer = $this->documentFooter())
            <x-slot:footer>
                {{ $footer }}
            </x-slot:footer>
        @endif
    </x-accounting.printable-document>
</div>
