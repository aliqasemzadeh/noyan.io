<?php

use App\Enums\Business\InvoiceOrientation;
use App\Models\Business;
use App\Support\Accounting\PreviewDocumentItem;
use App\Support\InvoiceDocumentFooter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new #[Layout('layouts::invoice')] class extends Component
{
    public Business $business;

    public function mount(Business $business): void
    {
        abort_unless(Auth::user()?->belongsToBusiness($business), 403);

        $this->business = $business->loadMissing('media', 'invoiceSettings');
    }

    /**
     * @return Collection<int, PreviewDocumentItem>
     */
    #[Computed]
    public function sampleItems(): Collection
    {
        return collect([
            new PreviewDocumentItem(
                __('general.invoice_preview_sample_item_1'),
                '1',
                '400000',
                '400000',
                sku: 'SKU-001',
                unit: 'piece',
                discount_amount: '0',
                tax_amount: '0',
            ),
            new PreviewDocumentItem(
                __('general.invoice_preview_sample_item_2'),
                '3',
                '80000',
                '240000',
                sku: 'SKU-002',
                unit: 'piece',
                discount_amount: '0',
                tax_amount: '0',
            ),
        ]);
    }

    public function documentFooter(): ?\Illuminate\Support\HtmlString
    {
        return InvoiceDocumentFooter::forBusiness($this->business);
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
        return $this->business->invoiceOrientation();
    }
};
?>

@php
    $orientation = $this->orientation();
@endphp

<x-slot name="title">{{ __('general.preview_quote') }} - {{ $business->name }}</x-slot>
<x-slot name="invoiceOrientation">{{ $orientation->value }}</x-slot>

<x-accounting.printable-document
    :business="$business"
    :document-label="__('general.quote')"
    :number="__('general.quote_preview_sample_number')"
    :party-name="__('general.invoice_preview_sample_party')"
    :dates="[
        ['label' => __('general.issue_date'), 'value' => $this->formatDate(now())],
        ['label' => __('general.valid_until'), 'value' => $this->formatDate(now()->addDays(14))],
    ]"
    :items="$this->sampleItems"
    items-total="640000"
    global-discount="0"
    global-tax="0"
    total-amount="640000"
    :note="__('general.invoice_preview_sample_note')"
    :orientation="$orientation"
    print-storage-key="quote-preview-print-settings"
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
        <div class="no-print rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            {{ __('general.invoice_preview_sample_notice') }}
        </div>
    </x-slot:banner>

    @if ($footer = $this->documentFooter())
        <x-slot:footer>
            {{ $footer }}
        </x-slot:footer>
    @endif
</x-accounting.printable-document>
