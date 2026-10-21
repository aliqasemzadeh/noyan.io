<?php

use App\Models\Accounting\BusinessSaleKind;
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

    #[On('panels.accounting.sale-kind.index.table')]
    public function refreshTable(): void
    {
        unset($this->businessSaleKinds);
    }

    #[Computed]
    public function businessSaleKinds(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return BusinessSaleKind::query()->whereRaw('1 = 0')->paginate(config('general.per_page', 15));
        }

        return BusinessSaleKind::query()
            ->with('saleKind')
            ->where('business_id', $businessId)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->whereHas('saleKind', function ($query) use ($search): void {
                    $query->where('code', 'like', $search)
                        ->orWhere('name', 'like', $search);
                });
            })
            ->latest()
            ->paginate(config('general.per_page', 15));
    }
};
?>

<x-slot name="title">{{ __('general.sale_kinds') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.sale_kinds') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <flux:heading size="xl" level="1">
                {{ __('general.sale_kinds') }}
            </flux:heading>

            @if (auth()->user()->current_business_id !== null)
                <flux:dropdown>
                    <flux:button icon:trailing="chevron-down" variant="primary" color="teal">
                        {{ __('general.options') }}
                    </flux:button>
                    <flux:menu>
                        <flux:modal.trigger name="accounting.sale-kind.activate">
                            <flux:menu.item icon="plus">{{ __('general.activate_sale_kind') }}</flux:menu.item>
                        </flux:modal.trigger>
                        <flux:menu.separator />
                        <flux:modal.trigger name="accounting.sale-kind.create-custom">
                            <flux:menu.item icon="sparkles">{{ __('general.create_custom_sale_kind') }}</flux:menu.item>
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

            <flux:table :paginate="$this->businessSaleKinds">
                <flux:table.columns>
                    <flux:table.column>{{ __('general.name') }}</flux:table.column>
                    <flux:table.column>{{ __('general.sale_kind_code') }}</flux:table.column>
                    <flux:table.column>{{ __('general.default') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->businessSaleKinds as $businessSaleKind)
                        <flux:table.row :key="$businessSaleKind->id">
                            <flux:table.cell>{{ $businessSaleKind->saleKind?->name }}</flux:table.cell>
                            <flux:table.cell dir="ltr">{{ $businessSaleKind->saleKind?->code }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($businessSaleKind->is_default)
                                    <flux:badge color="teal">{{ __('general.default') }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    @unless ($businessSaleKind->is_default)
                                        <flux:tooltip content="{{ __('general.set_as_default') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="star"
                                                icon:variant="outline"
                                                wire:click="$dispatch('panels.accounting.sale-kind.set-default', { id: {{ $businessSaleKind->id }} })"
                                            />
                                        </flux:tooltip>
                                    @endunless
                                    <flux:tooltip content="{{ __('general.deactivate') }}">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.accounting.sale-kind.deactivate.assign-data', { businessSaleKind: {{ $businessSaleKind->id }} })"
                                        />
                                    </flux:tooltip>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <livewire:accounting.sale-kind.activate :key="'sale-kind-activate'" />
        <livewire:accounting.sale-kind.create-custom :key="'sale-kind-create-custom'" />
        <livewire:accounting.sale-kind.deactivate :key="'sale-kind-deactivate'" />
        <livewire:accounting.sale-kind.set-default :key="'sale-kind-set-default'" />
    @endif
</div>
