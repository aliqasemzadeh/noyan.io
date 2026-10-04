<?php

use App\Enums\Accounting\JournalEntryStatus;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Support\LocaleDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $fiscalYearFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFiscalYearFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.journal-entry.index.table')]
    public function refreshTable(): void
    {
        unset($this->entries);
    }

    /**
     * @return LengthAwarePaginator<int, JournalEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return JournalEntry::query()
            ->with(['fiscalYear:id,name'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('voucher_number', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->fiscalYearFilter !== '', fn ($query) => $query->where('fiscal_year_id', $this->fiscalYearFilter))
            ->latest('entry_date')
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, FiscalYear>
     */
    #[Computed]
    public function fiscalYears(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return FiscalYear::query()
            ->where('business_id', $businessId)
            ->orderByDesc('start_date')
            ->get(['id', 'name']);
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

<x-slot name="title">{{ __('general.journal_entries') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.journal_entries') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.journal_entries') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.journal_entries_page_hint') }}</flux:text>
            </div>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                :href="route('accounting.journal-entries.create')"
                wire:navigate
            >
                {{ __('general.create_journal_entry') }}
            </flux:button>
        </div>
    </div>

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                @foreach (JournalEntryStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}" wire:key="journal-status-{{ $status->value }}">
                        {{ $status->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="fiscalYearFilter" searchable variant="listbox" placeholder="{{ __('general.all_fiscal_years') }}">
                <flux:select.option value="">{{ __('general.all_fiscal_years') }}</flux:select.option>
                @foreach ($this->fiscalYears as $fiscalYear)
                    <flux:select.option value="{{ $fiscalYear->id }}" wire:key="journal-fy-{{ $fiscalYear->id }}">
                        {{ $fiscalYear->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->entries">
            <flux:table.columns>
                <flux:table.column>{{ __('general.voucher_number') }}</flux:table.column>
                <flux:table.column>{{ __('general.journal_entry_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.fiscal_year') }}</flux:table.column>
                <flux:table.column>{{ __('general.description') }}</flux:table.column>
                <flux:table.column>{{ __('general.total_debit') }}</flux:table.column>
                <flux:table.column>{{ __('general.status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->entries as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell>
                            <span dir="ltr" class="font-medium">{{ $entry->voucher_number }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($entry->entry_date) }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->fiscalYear?->name }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="max-w-xs truncate">{{ $entry->description }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatAmount((string) $entry->total_debit) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$entry->status->badgeColor()">
                                {{ $entry->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @if ($entry->isDraft())
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="blue"
                                            icon="pencil"
                                            icon:variant="outline"
                                            :href="route('accounting.journal-entries.edit', $entry)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>
                                    <flux:tooltip content="{{ __('general.post_journal_entry') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="teal"
                                            icon="check"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.accounting.journal-entry.post.assign-data', { journalEntry: {{ $entry->id }} })"
                                        />
                                    </flux:tooltip>
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.accounting.journal-entry.delete.assign-data', { journalEntry: {{ $entry->id }} })"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            {{ __('general.no_journal_entries') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.journal-entry.delete :key="'journal-entry-delete'" />
    <livewire:accounting.journal-entry.post :key="'journal-entry-post'" />
</div>
