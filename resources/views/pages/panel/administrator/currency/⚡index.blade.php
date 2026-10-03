<?php

use App\Models\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.administrator.currency.index.table')]
    public function refreshTable(): void
    {
        unset($this->currencies);
    }

    #[Computed]
    public function currencies(): LengthAwarePaginator
    {
        return Currency::query()
            ->system()
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('symbol', 'like', $search);
                });
            })
            ->latest()
            ->paginate(15);
    }

    public function formatCreatedAt(Currency $currency): string
    {
        return Jalalian::fromDateTime($currency->created_at)->format('Y/m/d H:i');
    }
};
?>

<div class="space-y-6">
    <x-slot name="title">{{ __('general.currencies') }} - {{ __('general.app_name') }}</x-slot>

    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.system_management') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.currencies') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.currencies') }}
            </flux:heading>

            <flux:modal.trigger name="currency.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_currency') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:card>
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->currencies">
            <flux:table.columns>
                <flux:table.column>{{ __('general.currency_code') }}</flux:table.column>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.currency_symbol') }}</flux:table.column>
                <flux:table.column>{{ __('general.currency_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.decimal_places') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->currencies as $currency)
                    <flux:table.row :key="$currency->id">
                        <flux:table.cell>
                            <span dir="ltr">{{ $currency->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $currency->name }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $currency->symbol }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $currency->type->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $currency->decimal_places }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$currency->is_active ? 'green' : 'zinc'">
                                {{ $currency->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($currency) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.administrator.currency.edit.assign-data', { currency: {{ $currency->id }} })"
                                    />
                                </flux:tooltip>

                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.administrator.currency.delete.assign-data', { currency: {{ $currency->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            {{ __('general.no_currencies') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:currency.create :key="'currency-create'" />
    <livewire:currency.edit :key="'currency-edit'" />
    <livewire:currency.delete :key="'currency-delete'" />
</div>
