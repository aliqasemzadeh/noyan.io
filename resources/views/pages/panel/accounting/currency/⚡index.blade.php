<?php

use App\Models\Accounting\BusinessCurrency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Business Currencies')]
class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.currency.index.table')]
    public function refreshTable(): void
    {
        unset($this->businessCurrencies);
    }

    #[Computed]
    public function businessCurrencies(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return BusinessCurrency::query()->whereRaw('1 = 0')->paginate(config('general.per_page', 15));
        }

        return BusinessCurrency::query()
            ->with('currency')
            ->where('business_id', $businessId)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->whereHas('currency', function ($query) use ($search): void {
                    $query->where('code', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('symbol', 'like', $search);
                });
            })
            ->latest()
            ->paginate(config('general.per_page', 15));
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.business_currencies') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.business_currencies') }}
            </flux:heading>

            @if (auth()->user()->current_business_id !== null)
                <flux:dropdown>
                    <flux:button icon:trailing="chevron-down" variant="primary" color="teal">
                        {{ __('general.options') }}
                    </flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="accounting.currency.activate">
                            <flux:menu.item icon="plus">{{ __('general.activate_currency') }}</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:menu.separator />
                        <flux:modal.trigger name="accounting.currency.create-custom">
                            <flux:menu.item icon="sparkles">{{ __('general.create_custom_asset') }}</flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    @if (auth()->user()->current_business_id === null)
        <flux:callout icon="building" variant="warning">
            {{ __('general.business_required') }}
        </flux:callout>
    @else
        <flux:card>
            <div class="mb-4">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="search"
                    placeholder="{{ __('general.search') }}..."
                    clearable
                />
            </div>

            <flux:table :paginate="$this->businessCurrencies">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.currency_code') }}</flux:table.column>
                    <flux:table.column>{{ __('general.name') }}</flux:table.column>
                    <flux:table.column>{{ __('general.currency_type') }}</flux:table.column>
                    <flux:table.column>{{ __('general.decimal_places') }}</flux:table.column>
                    <flux:table.column>{{ __('general.base_currency') }}</flux:table.column>
                    <flux:table.column>{{ __('general.exchange_rate_to_base') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->businessCurrencies as $businessCurrency)
                        <flux:table.row :key="$businessCurrency->id">
                            <flux:table.cell>
                                <span dir="ltr">{{ $businessCurrency->currency->code }}</span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $businessCurrency->currency->name }}</flux:table.cell>
                            <flux:table.cell>{{ $businessCurrency->currency->type->label() }}</flux:table.cell>
                            <flux:table.cell>
                                <span dir="ltr">{{ $businessCurrency->currency->decimal_places }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($businessCurrency->is_base)
                                    <flux:badge size="sm" color="teal">{{ __('general.base_currency') }}</flux:badge>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <span dir="ltr">{{ $businessCurrency->is_base ? '1' : $businessCurrency->exchange_rate_to_base }}</span>
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
                                            wire:click="$dispatch('panels.accounting.currency.edit.assign-data', { businessCurrency: {{ $businessCurrency->id }} })"
                                        />
                                    </flux:tooltip>

                                    @if (! $businessCurrency->is_base)
                                        <flux:tooltip content="{{ __('general.deactivate_currency') }}">
                                            <flux:button
                                                size="xs"
                                                variant="danger"
                                                icon="trash"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.accounting.currency.deactivate.assign-data', { businessCurrency: {{ $businessCurrency->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">
                                {{ __('general.no_business_currencies') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <livewire:accounting.currency.activate :key="'accounting-currency-activate'" />
    <livewire:accounting.currency.create-custom :key="'accounting-currency-create-custom'" />
    <livewire:accounting.currency.edit :key="'accounting-currency-edit'" />
    <livewire:accounting.currency.deactivate :key="'accounting-currency-deactivate'" />
</div>
