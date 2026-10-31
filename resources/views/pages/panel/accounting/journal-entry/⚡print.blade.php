<?php

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\InvoiceOrientation;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Support\InvoiceDocumentFooter;
use App\Support\LocaleDate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::journal')] class extends Component
{
    public JournalEntry $journalEntry;

    public function mount(JournalEntry $journalEntry): void
    {
        Auth::user()?->ensureCurrentBusiness();

        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::JournalEntryView), 403);
        abort_unless(
            (int) $journalEntry->business_id === (int) Auth::user()?->current_business_id,
            404
        );

        $this->journalEntry = $journalEntry->load([
            'business.media',
            'business.invoiceSettings',
            'fiscalYear:id,name',
            'creator:id,first_name,last_name,mobile',
            'lines.glAccount:id,code,name,full_code',
            'lines.account:id,name',
            'lines.party:id,name',
            'lines.category:id,name',
            'lines.costCenter:id,name',
            'lines.project:id,name',
            'referenceable',
        ]);
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        return LocaleDate::formatDate($date);
    }

    public function formatAmount(?string $amount): string
    {
        $amount = (string) ($amount ?? '0');

        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }

    public function sourceLabel(): string
    {
        $referenceable = $this->journalEntry->referenceable;

        if ($referenceable instanceof Invoice) {
            return __('general.journal_linked_invoice').' '.$referenceable->invoice_number;
        }

        if ($referenceable instanceof Transaction) {
            return __('general.journal_linked_transaction').' #'.$referenceable->id;
        }

        if ($referenceable !== null) {
            return __('general.journal_entry_source_linked', [
                'type' => class_basename($referenceable),
                'id' => $referenceable->getKey(),
            ]);
        }

        return __('general.journal_entry_source_manual');
    }

    public function documentFooter(): ?\Illuminate\Support\HtmlString
    {
        return InvoiceDocumentFooter::forBusiness($this->journalEntry->business);
    }

    public function orientation(): InvoiceOrientation
    {
        return $this->journalEntry->business->invoiceOrientation();
    }
};
?>

@php
    $business = $journalEntry->business;
    $orientation = $this->orientation();
    $totalDebit = (string) $journalEntry->lines->sum('debit');
    $totalCredit = (string) $journalEntry->lines->sum('credit');
@endphp

<x-slot name="title">{{ __('general.print_journal_entry') }} {{ $journalEntry->voucher_number }} - {{ $business->name }}</x-slot>
<x-slot name="journalOrientation">{{ $orientation->value }}</x-slot>

<x-accounting.printable-journal-entry
    :business="$business"
    :journal-entry="$journalEntry"
    :lines="$journalEntry->lines"
    :total-debit="$totalDebit"
    :total-credit="$totalCredit"
    :source-label="$this->sourceLabel()"
    :orientation="$orientation"
    print-storage-key="journal-entry-print-settings"
    :format-amount="fn (?string $amount): string => $this->formatAmount($amount)"
    :format-date="fn (\DateTimeInterface|string|null $date): string => $this->formatDate($date)"
>
    <x-slot:toolbar>
        <flux:button
            type="button"
            variant="primary"
            color="teal"
            icon="printer"
            x-on:click="window.print()"
        >
            {{ __('general.print_journal_entry') }}
        </flux:button>
    </x-slot:toolbar>

    @if ($footer = $this->documentFooter())
        <x-slot:footer>
            {{ $footer }}
        </x-slot:footer>
    @endif
</x-accounting.printable-journal-entry>
