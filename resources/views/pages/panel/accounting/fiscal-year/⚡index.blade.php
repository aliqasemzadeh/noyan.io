<?php

use App\Models\Accounting\FiscalYear;
use App\Support\LocaleDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public string $closedFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClosedFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.fiscal-year.index.table')]
    public function refreshTable(): void
    {
        unset($this->fiscalYears);
    }

    /**
     * @return LengthAwarePaginator<int, FiscalYear>
     */
    #[Computed]
    public function fiscalYears(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return FiscalYear::query()
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where('name', 'like', $search);
            })
            ->when($this->closedFilter === 'closed', fn ($query) => $query->where('is_closed', true))
            ->when($this->closedFilter === 'open', fn ($query) => $query->where('is_closed', false))
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->paginate(15);
    }

    public function formatDate(\DateTimeInterface|string|null $date): string
    {
        return LocaleDate::formatDate($date);
    }
};
?>

<x-slot name="title">{{ __('general.fiscal_years') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.fiscal_years') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.fiscal_years') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.fiscal_years_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="fiscal-year.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_fiscal_year') }}
                </flux:button>
            </flux:modal.trigger>
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

            <flux:select wire:model.live="closedFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="open">{{ __('general.fiscal_year_open_badge') }}</flux:select.option>
                <flux:select.option value="closed">{{ __('general.fiscal_year_closed_badge') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->fiscalYears">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.fiscal_year_start_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.fiscal_year_end_date') }}</flux:table.column>
                <flux:table.column>{{ __('general.fiscal_year_is_closed') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->fiscalYears as $fiscalYear)
                    <flux:table.row :key="$fiscalYear->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $fiscalYear->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($fiscalYear->start_date) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->formatDate($fiscalYear->end_date) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$fiscalYear->is_closed ? 'zinc' : 'green'">
                                {{ $fiscalYear->is_closed ? __('general.fiscal_year_closed_badge') : __('general.fiscal_year_open_badge') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.fiscal-year.edit.assign-data', { fiscalYear: {{ $fiscalYear->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.fiscal-year.delete.assign-data', { fiscalYear: {{ $fiscalYear->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            {{ __('general.no_fiscal_years') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.fiscal-year.create :key="'fiscal-year-create'" />
    <livewire:accounting.fiscal-year.edit :key="'fiscal-year-edit'" />
    <livewire:accounting.fiscal-year.delete :key="'fiscal-year-delete'" />
</div>
