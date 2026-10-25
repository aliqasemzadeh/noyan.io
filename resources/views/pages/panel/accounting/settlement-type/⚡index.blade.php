<?php

use App\Models\Accounting\BusinessSettlementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.settlement-type.index.table')]
    public function refreshTable(): void
    {
        unset($this->businessSettlementTypes);
    }

    #[Computed]
    public function businessSettlementTypes(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return BusinessSettlementType::query()->whereRaw('1 = 0')->paginate(config('general.per_page', 15));
        }

        return BusinessSettlementType::query()
            ->with('settlementType')
            ->where('business_id', $businessId)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->whereHas('settlementType', function ($query) use ($search): void {
                    $query->where('code', 'like', $search)
                        ->orWhere('name', 'like', $search);
                });
            })
            ->latest()
            ->paginate(config('general.per_page', 15));
    }
};
?>

<x-slot name="title">{{ __('general.settlement_types') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.settlement_types') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.settlement_types') }}
            </flux:heading>

            @if (auth()->user()->current_business_id !== null)
                <flux:dropdown>
                    <flux:button icon:trailing="chevron-down" variant="primary" color="teal">
                        {{ __('general.options') }}
                    </flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="accounting.settlement-type.activate">
                            <flux:menu.item icon="plus">{{ __('general.activate_settlement_type') }}</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:menu.separator />
                        <flux:modal.trigger name="accounting.settlement-type.create-custom">
                            <flux:menu.item icon="sparkles">{{ __('general.create_custom_settlement_type') }}</flux:menu.item>
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

            <flux:table :paginate="$this->businessSettlementTypes">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.name') }}</flux:table.column>
                    <flux:table.column>{{ __('general.settlement_type_code') }}</flux:table.column>
                    <flux:table.column>{{ __('general.default') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->businessSettlementTypes as $businessSettlementType)
                        <flux:table.row :key="$businessSettlementType->id">
                            <flux:table.cell>{{ $businessSettlementType->settlementType?->name }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $businessSettlementType->settlementType?->code }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($businessSettlementType->is_default)
                                    <flux:badge color="teal">{{ __('general.default') }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    @unless ($businessSettlementType->is_default)
                                        <flux:tooltip content="{{ __('general.set_as_default') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="star"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.accounting.settlement-type.set-default', { id: {{ $businessSettlementType->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endunless
                                    <flux:tooltip content="{{ __('general.deactivate') }}">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.accounting.settlement-type.deactivate.assign-data', { businessSettlementType: {{ $businessSettlementType->id }} })"
                                        />
                                    </flux:tooltip>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <livewire:accounting.settlement-type.activate :key="'settlement-type-activate'" />
        <livewire:accounting.settlement-type.create-custom :key="'settlement-type-create-custom'" />
        <livewire:accounting.settlement-type.deactivate :key="'settlement-type-deactivate'" />
        <livewire:accounting.settlement-type.set-default :key="'settlement-type-set-default'" />
    @endif
</div>
