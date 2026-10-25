<?php

use App\Livewire\Forms\Catalog\StockOperationForm;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Project;
use App\Models\Catalog\Product;
use App\Models\Catalog\Warehouse;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public StockOperationForm $form;

    public string $title = '';

    #[On('panels.warehouse.stock.operation.open')]
    public function open(string $mode): void
    {
        $this->form->resetForMode($mode);
        $this->title = match ($mode) {
            'receive' => __('general.stock_receive'),
            'issue' => __('general.stock_issue'),
            'transfer' => __('general.stock_transfer'),
            'adjust' => __('general.stock_adjust'),
            'allocate' => __('general.stock_allocate'),
            default => __('general.stock_operation'),
        };

        Flux::modal('warehouse.stock.operation')->show();
    }

    public function save(): void
    {
        $this->form->submit();
        $this->dispatch('panels.warehouse.stock.refresh');
        Flux::modals()->close();
        Flux::toast(__('general.stock_operation_saved'));
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouses(): Collection
    {
        return Warehouse::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::cachedOptionsForBusiness((int) Auth::user()?->current_business_id);
    }

    /**
     * @return Collection<int, CostCenter>
     */
    #[Computed]
    public function costCenters(): Collection
    {
        return CostCenter::cachedOptionsForBusiness((int) Auth::user()?->current_business_id);
    }
};
?>

<flux:modal name="warehouse.stock.operation" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ $title }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.product') }}</flux:label>
            <flux:select wire:model="form.product_id" variant="listbox" searchable placeholder="{{ __('general.select') }}">
                @foreach ($this->products as $product)
                    <flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.product_id" />
        </flux:field>

        @if (in_array($form->mode, ['receive', 'issue', 'adjust'], true))
            <flux:field>
                <flux:label>{{ __('general.warehouse') }}</flux:label>
                <flux:select wire:model="form.warehouse_id" variant="listbox" searchable>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.warehouse_id" />
            </flux:field>
        @endif

        @if ($form->mode === 'transfer')
            <flux:field>
                <flux:label>{{ __('general.source_warehouse') }}</flux:label>
                <flux:select wire:model="form.source_warehouse_id" variant="listbox" searchable>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.source_warehouse_id" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.destination_warehouse') }}</flux:label>
                <flux:select wire:model="form.destination_warehouse_id" variant="listbox" searchable>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.destination_warehouse_id" />
            </flux:field>
        @endif

        @if ($form->mode === 'allocate')
            <flux:field>
                <flux:label>{{ __('general.source_warehouse') }}</flux:label>
                <flux:select wire:model="form.source_warehouse_id" variant="listbox" searchable clearable>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.destination_warehouse') }}</flux:label>
                <flux:select wire:model="form.destination_warehouse_id" variant="listbox" searchable clearable>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.project') }}</flux:label>
                <flux:select wire:model="form.project_id" variant="listbox" searchable clearable>
                    @foreach ($this->projects as $project)
                        <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.project_id" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('general.cost_center') }}</flux:label>
                <flux:select wire:model="form.cost_center_id" variant="listbox" searchable clearable>
                    @foreach ($this->costCenters as $costCenter)
                        <flux:select.option value="{{ $costCenter->id }}">{{ $costCenter->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field variant="inline">
                <flux:label>{{ __('general.consume_stock') }}</flux:label>
                <flux:switch wire:model="form.consume" />
            </flux:field>
        @endif

        <flux:field>
            <flux:label>{{ __('general.quantity') }}</flux:label>
            <flux:input wire:model="form.quantity" dir="ltr" />
            <flux:error name="form.quantity" />
            @if ($form->mode === 'adjust')
                <flux:description>{{ __('general.stock_adjust_hint') }}</flux:description>
            @endif
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.unit_cost') }}</flux:label>
            <flux:input wire:model="form.unit_cost" dir="ltr" />
            <flux:error name="form.unit_cost" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.note') }}</flux:label>
            <flux:textarea wire:model="form.note" rows="3" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
