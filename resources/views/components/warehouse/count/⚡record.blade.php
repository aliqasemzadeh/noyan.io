<?php

use App\Actions\Inventory\RecordStockCountAction;
use App\Enums\Business\BusinessPermission;
use App\Models\Business;
use App\Models\Catalog\InventoryStock;
use App\Models\Catalog\Warehouse;
use App\Support\Warehouse\Quantity;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $warehouse_id = '';

    public string $note = '';

    /**
     * @var list<array{product_id: int, name: string, sku: string, system_quantity: string, counted_quantity: string}>
     */
    public array $lines = [];

    #[On('panels.warehouse.count.record.open')]
    public function open(): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockCount), 403);

        $this->reset('warehouse_id', 'note', 'lines');
        $this->warehouse_id = '';
        Flux::modal('warehouse.count.record')->show();
    }

    public function updatedWarehouseId(): void
    {
        if ($this->warehouse_id === '') {
            $this->lines = [];

            return;
        }

        $this->lines = InventoryStock::query()
            ->with('product:id,name,sku')
            ->where('business_id', Auth::user()?->current_business_id)
            ->where('warehouse_id', $this->warehouse_id)
            ->where('physical_quantity', '>', 0)
            ->orderBy('product_id')
            ->get()
            ->map(fn (InventoryStock $stock): array => [
                'product_id' => (int) $stock->product_id,
                'name' => (string) ($stock->product?->name ?? ''),
                'sku' => (string) ($stock->product?->sku ?? ''),
                'system_quantity' => Quantity::format((string) $stock->physical_quantity),
                'counted_quantity' => Quantity::format((string) $stock->physical_quantity),
            ])
            ->all();
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->canInBusiness(BusinessPermission::StockCount), 403);

        $business = Business::query()->findOrFail(Auth::user()?->current_business_id);

        app(RecordStockCountAction::class)->handle(
            business: $business,
            warehouseId: (int) $this->warehouse_id,
            lines: collect($this->lines)->map(fn (array $line): array => [
                'product_id' => $line['product_id'],
                'counted_quantity' => $line['counted_quantity'],
            ])->all(),
            userId: Auth::id(),
            note: filled($this->note) ? $this->note : null,
        );

        $this->dispatch('panels.warehouse.documents.refresh');
        Flux::modals()->close();
        Flux::toast(__('general.stock_count_recorded'));
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
            ->orderBy('name')
            ->get(['id', 'name']);
    }
};
?>

<flux:modal name="warehouse.count.record" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.record_stock_count') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-4">
        <flux:field>
            <flux:label>{{ __('general.warehouse') }}</flux:label>
            <flux:select wire:model.live="warehouse_id" variant="listbox" searchable placeholder="{{ __('general.select') }}">
                @foreach ($this->warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="warehouse_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.note') }}</flux:label>
            <flux:textarea wire:model="note" rows="2" />
        </flux:field>

        @foreach ($lines as $index => $line)
            <div wire:key="count-line-{{ $line['product_id'] }}" class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="font-medium">{{ $line['name'] }}</div>
                <div class="text-sm text-zinc-500" dir="ltr">{{ $line['sku'] }}</div>
                <div class="text-sm">{{ __('general.system_quantity') }}: <span dir="ltr">{{ $line['system_quantity'] }}</span></div>
                <flux:field>
                    <flux:label>{{ __('general.counted_quantity') }}</flux:label>
                    <flux:input wire:model="lines.{{ $index }}.counted_quantity" dir="ltr" />
                    <flux:error name="lines.{{ $index }}.counted_quantity" />
                </flux:field>
            </div>
        @endforeach

        <flux:button type="submit" variant="primary" color="amber" class="w-full">{{ __('general.record_stock_count') }}</flux:button>
    </form>
</flux:modal>
