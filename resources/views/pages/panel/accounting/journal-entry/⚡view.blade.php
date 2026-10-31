<?php

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\BusinessUser;
use App\Support\LocaleDate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
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

        $this->journalEntry = $this->loadEntry($journalEntry);
    }

    protected function loadEntry(JournalEntry $journalEntry): JournalEntry
    {
        return $journalEntry->load([
            'fiscalYear:id,name',
            'creator:id,first_name,last_name,mobile',
            'lines.glAccount:id,code,name,full_code',
            'lines.account:id,name',
            'lines.party:id,name',
            'lines.category:id,name',
            'lines.costCenter:id,name',
            'lines.project:id,name',
            'lines.details.glDetail',
            'referenceable',
        ])->loadMorph('referenceable', [
            Transaction::class => [
                'party:id,name',
                'account:id,name',
                'destinationAccount:id,name',
                'category:id,name',
                'invoice:id,invoice_number,type,total_amount,payment_status,party_name,issue_date,due_date,paid_amount,note',
                'invoice.party:id,name',
                'loan:id,title,type,status,principal_amount,interest_amount,total_amount,paid_amount,issue_date,description',
                'loan.party:id,name',
                'loan.account:id,name',
                'cheque:id,cheque_number,sayad_number,type,status,amount,bank_name,bank_branch,issue_date,due_date,note',
                'cheque.party:id,name',
                'cheque.account:id,name',
            ],
            Invoice::class => [
                'party:id,name',
            ],
        ]);
    }

    public function canEditEntry(): bool
    {
        if (! $this->journalEntry->isOpen() || $this->journalEntry->isSystemGenerated()) {
            return false;
        }

        return Auth::user()?->canInBusiness(BusinessPermission::JournalEntryEdit)
            || $this->currentUserIsBusinessOwner();
    }

    public function currentUserIsBusinessOwner(): bool
    {
        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null) {
            return false;
        }

        if ((int) $user->currentBusiness?->owner_id === (int) $user->id) {
            return true;
        }

        $membership = BusinessUser::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->first();

        return $membership !== null && $membership->hasRole(BusinessRole::Owner);
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
};
?>

<x-slot name="title">{{ __('general.journal_entry') }} {{ $journalEntry->voucher_number }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.journal-entries.index')" wire:navigate>
                {{ __('general.journal_entries') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                <span dir="ltr">{{ $journalEntry->voucher_number }}</span>
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.journal_entry_details') }}</flux:heading>
                <flux:text class="mt-1">
                    <span dir="ltr" class="font-medium">{{ $journalEntry->voucher_number }}</span>
                    — {{ $journalEntry->description }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button
                    variant="primary"
                    color="zinc"
                    icon="printer"
                    :href="route('accounting.journal-entries.print', $journalEntry)"
                    target="_blank"
                >
                    {{ __('general.print_journal_entry') }}
                </flux:button>

                @if ($this->canEditEntry())
                    <flux:button
                        variant="primary"
                        color="blue"
                        icon="pencil"
                        :href="route('accounting.journal-entries.edit', $journalEntry)"
                        wire:navigate
                    >
                        {{ __('general.edit') }}
                    </flux:button>
                @endif

                <flux:button
                    variant="ghost"
                    icon="arrow-left"
                    :href="route('accounting.journal-entries.index')"
                    wire:navigate
                >
                    {{ __('general.back') }}
                </flux:button>
            </div>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <flux:text class="text-xs text-zinc-500">{{ __('general.journal_entry_date') }}</flux:text>
                <div class="mt-1 font-medium">{{ $this->formatDate($journalEntry->entry_date) }}</div>
            </div>
            <div>
                <flux:text class="text-xs text-zinc-500">{{ __('general.fiscal_year') }}</flux:text>
                <div class="mt-1 font-medium">{{ $journalEntry->fiscalYear?->name ?? '—' }}</div>
            </div>
            <div>
                <flux:text class="text-xs text-zinc-500">{{ __('general.status') }}</flux:text>
                <div class="mt-1">
                    <flux:badge size="sm" :color="$journalEntry->status->badgeColor()">
                        {{ $journalEntry->status->label() }}
                    </flux:badge>
                </div>
            </div>
            <div>
                <flux:text class="text-xs text-zinc-500">{{ __('general.journal_source') }}</flux:text>
                <div class="mt-1 font-medium">{{ $this->sourceLabel() }}</div>
            </div>
            <div>
                <flux:text class="text-xs text-zinc-500">{{ __('general.journal_creator') }}</flux:text>
                <div class="mt-1 font-medium">{{ $journalEntry->creator?->fullName() ?: ($journalEntry->creator?->mobile ?? '—') }}</div>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <flux:text class="text-xs text-zinc-500">{{ __('general.description') }}</flux:text>
                <div class="mt-1 font-medium">{{ $journalEntry->description }}</div>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="sm" class="mb-3">{{ __('general.journal_lines') }}</flux:heading>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 text-start font-medium">{{ __('general.row') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('general.gl_account') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('general.journal_line_details') }}</th>
                        <th class="px-3 py-2 text-end font-medium">{{ __('general.debit') }}</th>
                        <th class="px-3 py-2 text-end font-medium">{{ __('general.credit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($journalEntry->lines as $index => $line)
                        <tr wire:key="journal-view-line-{{ $line->id }}">
                            <td class="px-3 py-2 align-top">{{ $index + 1 }}</td>
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium">{{ $line->glAccount?->name }}</div>
                                <div class="text-xs text-zinc-500" dir="ltr">{{ $line->glAccount?->full_code ?? $line->glAccount?->code }}</div>
                                @if (filled($line->description))
                                    <div class="mt-1 text-xs text-zinc-500">{{ $line->description }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top text-zinc-600 dark:text-zinc-300">
                                @if ($line->account)
                                    <div>{{ $line->account->name }}</div>
                                @endif
                                @if ($line->party)
                                    <div>{{ $line->party->name }}</div>
                                @endif
                                @if ($line->category)
                                    <div>{{ $line->category->name }}</div>
                                @endif
                                @if ($line->costCenter)
                                    <div>{{ $line->costCenter->name }}</div>
                                @endif
                                @if ($line->project)
                                    <div>{{ $line->project->name }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top text-end" dir="ltr">{{ $this->formatAmount((string) $line->debit) }}</td>
                            <td class="px-3 py-2 align-top text-end" dir="ltr">{{ $this->formatAmount((string) $line->credit) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-zinc-50 font-medium dark:bg-zinc-800/80">
                    <tr>
                        <td class="px-3 py-2" colspan="3">{{ __('general.total') }}</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ $this->formatAmount((string) $journalEntry->lines->sum('debit')) }}</td>
                        <td class="px-3 py-2 text-end" dir="ltr">{{ $this->formatAmount((string) $journalEntry->lines->sum('credit')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="sm" class="mb-4">{{ __('general.journal_linked_entities') }}</flux:heading>

        @php
            $referenceable = $journalEntry->referenceable;
        @endphp

        @if ($referenceable === null)
            <flux:text>{{ __('general.journal_entry_source_manual') }}</flux:text>
        @else
            <div class="space-y-4">
                @if ($referenceable instanceof \App\Models\Accounting\Invoice)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('general.journal_linked_invoice') }}</flux:heading>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.number') }}</flux:text>
                                <div class="mt-1 font-medium" dir="ltr">{{ $referenceable->invoice_number }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.type') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $referenceable->type->label() }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.status') }}</flux:text>
                                <div class="mt-1">
                                    <flux:badge size="sm" :color="$referenceable->payment_status->badgeColor()">
                                        {{ $referenceable->payment_status->label() }}
                                    </flux:badge>
                                </div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.party') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $referenceable->displayPartyName() }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.issue_date') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $this->formatDate($referenceable->issue_date) }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.due_date') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $this->formatDate($referenceable->due_date) }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.total') }}</flux:text>
                                <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $referenceable->total_amount) }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.paid_amount') }}</flux:text>
                                <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $referenceable->paid_amount) }}</div>
                            </div>
                            @if (filled($referenceable->note))
                                <div class="sm:col-span-2">
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.note') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $referenceable->note }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($referenceable instanceof \App\Models\Accounting\Transaction)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('general.journal_linked_transaction') }}</flux:heading>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.type') }}</flux:text>
                                <div class="mt-1">
                                    <flux:badge size="sm" :color="$referenceable->type->badgeColor()">
                                        {{ $referenceable->type->label() }}
                                    </flux:badge>
                                </div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">#</flux:text>
                                <div class="mt-1 font-medium" dir="ltr">{{ $referenceable->id }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.date') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $this->formatDate($referenceable->transaction_date) }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.amount') }}</flux:text>
                                <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $referenceable->amount) }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.account') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $referenceable->account?->name ?? '—' }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.destination_account') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $referenceable->destinationAccount?->name ?? '—' }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.party') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $referenceable->party?->name ?? '—' }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.category') }}</flux:text>
                                <div class="mt-1 font-medium">{{ $referenceable->category?->name ?? '—' }}</div>
                            </div>
                            <div>
                                <flux:text class="text-xs text-zinc-500">{{ __('general.reference_number') }}</flux:text>
                                <div class="mt-1 font-medium" dir="ltr">{{ $referenceable->reference_number ?: '—' }}</div>
                            </div>
                            @if (filled($referenceable->note))
                                <div class="sm:col-span-2 lg:col-span-3">
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.note') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $referenceable->note }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($referenceable->invoice)
                        @php $invoice = $referenceable->invoice; @endphp
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('general.journal_linked_invoice') }}</flux:heading>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.number') }}</flux:text>
                                    <div class="mt-1 font-medium" dir="ltr">{{ $invoice->invoice_number }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.type') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $invoice->type->label() }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.party') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $invoice->displayPartyName() }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.total') }}</flux:text>
                                    <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $invoice->total_amount) }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.status') }}</flux:text>
                                    <div class="mt-1">
                                        <flux:badge size="sm" :color="$invoice->payment_status->badgeColor()">
                                            {{ $invoice->payment_status->label() }}
                                        </flux:badge>
                                    </div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.issue_date') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $this->formatDate($invoice->issue_date) }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($referenceable->loan)
                        @php $loan = $referenceable->loan; @endphp
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('general.journal_linked_loan') }}</flux:heading>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.title') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $loan->title }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.type') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $loan->type->label() }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.status') }}</flux:text>
                                    <div class="mt-1">
                                        <flux:badge size="sm" :color="$loan->status->badgeColor()">
                                            {{ $loan->status->label() }}
                                        </flux:badge>
                                    </div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.party') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $loan->party?->name ?? '—' }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.account') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $loan->account?->name ?? '—' }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.total') }}</flux:text>
                                    <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $loan->total_amount) }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.paid_amount') }}</flux:text>
                                    <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $loan->paid_amount) }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.issue_date') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $this->formatDate($loan->issue_date) }}</div>
                                </div>
                                @if (filled($loan->description))
                                    <div class="sm:col-span-2">
                                        <flux:text class="text-xs text-zinc-500">{{ __('general.description') }}</flux:text>
                                        <div class="mt-1 font-medium">{{ $loan->description }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($referenceable->cheque)
                        @php $cheque = $referenceable->cheque; @endphp
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('general.journal_linked_cheque') }}</flux:heading>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.cheque_number') }}</flux:text>
                                    <div class="mt-1 font-medium" dir="ltr">{{ $cheque->cheque_number }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.type') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $cheque->type->label() }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.status') }}</flux:text>
                                    <div class="mt-1">
                                        <flux:badge size="sm" :color="$cheque->status->badgeColor()">
                                            {{ $cheque->status->label() }}
                                        </flux:badge>
                                    </div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.amount') }}</flux:text>
                                    <div class="mt-1 font-medium" dir="ltr">{{ $this->formatAmount((string) $cheque->amount) }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.party') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $cheque->party?->name ?? '—' }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.account') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $cheque->account?->name ?? '—' }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.bank_name') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $cheque->bank_name ?: '—' }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.issue_date') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $this->formatDate($cheque->issue_date) }}</div>
                                </div>
                                <div>
                                    <flux:text class="text-xs text-zinc-500">{{ __('general.due_date') }}</flux:text>
                                    <div class="mt-1 font-medium">{{ $this->formatDate($cheque->due_date) }}</div>
                                </div>
                                @if (filled($cheque->sayad_number))
                                    <div>
                                        <flux:text class="text-xs text-zinc-500">{{ __('general.sayad_number') }}</flux:text>
                                        <div class="mt-1 font-medium" dir="ltr">{{ $cheque->sayad_number }}</div>
                                    </div>
                                @endif
                                @if (filled($cheque->note))
                                    <div class="sm:col-span-2">
                                        <flux:text class="text-xs text-zinc-500">{{ __('general.note') }}</flux:text>
                                        <div class="mt-1 font-medium">{{ $cheque->note }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif

                @if (! $referenceable instanceof \App\Models\Accounting\Invoice && ! $referenceable instanceof \App\Models\Accounting\Transaction)
                    <flux:text>
                        {{ __('general.journal_entry_source_linked', [
                            'type' => class_basename($referenceable),
                            'id' => $referenceable->getKey(),
                        ]) }}
                    </flux:text>
                @endif
            </div>
        @endif
    </flux:card>
</div>
