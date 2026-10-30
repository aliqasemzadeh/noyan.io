<?php

use App\Models\Accounting\JournalEntry;
use App\Support\LocaleDate;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?JournalEntry $journalEntry = null;

    #[On('panels.accounting.journal-entry.view.assign-data')]
    public function assignData(JournalEntry $journalEntry): void
    {
        abort_unless(
            (int) $journalEntry->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->journalEntry = $journalEntry->load([
            'fiscalYear:id,name',
            'referenceable',
            'lines.glAccount:id,code,name,full_code',
            'lines.account:id,name',
            'lines.party:id,name',
            'lines.category:id,name',
        ]);

        Flux::modal('journal-entry.view')->show();
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
};
?>

<flux:modal name="journal-entry.view" flyout position="right" class="md:w-[32rem] lg:w-[40rem]">
    @if ($journalEntry)
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.journal_entry_details') }}</flux:heading>
                <flux:text class="mt-1">
                    <span dir="ltr" class="font-medium">{{ $journalEntry->voucher_number }}</span>
                    — {{ $journalEntry->description }}
                </flux:text>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <flux:text class="text-xs text-zinc-500">{{ __('general.journal_entry_date') }}</flux:text>
                    <div class="mt-1 font-medium">{{ $this->formatDate($journalEntry->entry_date) }}</div>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500">{{ __('general.fiscal_year') }}</flux:text>
                    <div class="mt-1 font-medium">{{ $journalEntry->fiscalYear?->name }}</div>
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
                    <flux:text class="text-xs text-zinc-500">{{ __('general.journal_line_details') }}</flux:text>
                    <div class="mt-1 font-medium">
                        @if ($journalEntry->referenceable)
                            {{ __('general.journal_entry_source_linked', [
                                'type' => class_basename($journalEntry->referenceable_type),
                                'id' => $journalEntry->referenceable_id,
                            ]) }}
                        @else
                            {{ __('general.journal_entry_source_manual') }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <flux:heading size="sm">{{ __('general.journal_lines') }}</flux:heading>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-300">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium">{{ __('general.gl_account') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('general.journal_line_details') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('general.debit') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('general.credit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($journalEntry->lines as $line)
                                <tr wire:key="journal-view-line-{{ $line->id }}">
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
                                    </td>
                                    <td class="px-3 py-2 align-top text-end" dir="ltr">{{ $this->formatAmount((string) $line->debit) }}</td>
                                    <td class="px-3 py-2 align-top text-end" dir="ltr">{{ $this->formatAmount((string) $line->credit) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-zinc-50 font-medium dark:bg-zinc-800/80">
                            <tr>
                                <td class="px-3 py-2" colspan="2">{{ __('general.total') }}</td>
                                <td class="px-3 py-2 text-end" dir="ltr">{{ $this->formatAmount((string) $journalEntry->lines->sum('debit')) }}</td>
                                <td class="px-3 py-2 text-end" dir="ltr">{{ $this->formatAmount((string) $journalEntry->lines->sum('credit')) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button type="button" variant="primary" color="zinc" class="w-full sm:w-auto">
                        {{ __('general.close') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    @endif
</flux:modal>
