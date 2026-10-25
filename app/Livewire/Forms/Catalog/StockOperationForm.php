<?php

namespace App\Livewire\Forms\Catalog;

use App\Actions\Inventory\AdjustStockAction;
use App\Actions\Inventory\AllocateStockAction;
use App\Actions\Inventory\TransferStockAction;
use App\Enums\Catalog\StockMovementType;
use App\Models\Business;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class StockOperationForm extends Form
{
    public string $mode = 'receive';

    public ?int $product_id = null;

    public ?int $warehouse_id = null;

    public ?int $source_warehouse_id = null;

    public ?int $destination_warehouse_id = null;

    public ?int $project_id = null;

    public ?int $cost_center_id = null;

    public string $quantity = '';

    public string $unit_cost = '0';

    public string $note = '';

    public bool $consume = false;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        $rules = [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->where('track_inventory', true)
                    ->whereNull('deleted_at')),
            ],
            'quantity' => $this->mode === 'adjust'
                ? ['required', 'numeric', 'not_in:0']
                : ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];

        return match ($this->mode) {
            'transfer' => [
                ...$rules,
                'source_warehouse_id' => [
                    'required',
                    'integer',
                    Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
                'destination_warehouse_id' => [
                    'required',
                    'integer',
                    'different:source_warehouse_id',
                    Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
            ],
            'allocate' => [
                ...$rules,
                'source_warehouse_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
                'destination_warehouse_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
                'project_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('projects', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
                'cost_center_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('cost_centers', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
                'consume' => ['boolean'],
            ],
            default => [
                ...$rules,
                'warehouse_id' => [
                    'required',
                    'integer',
                    Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')),
                ],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'product_id' => __('general.product'),
            'warehouse_id' => __('general.warehouse'),
            'source_warehouse_id' => __('general.source_warehouse'),
            'destination_warehouse_id' => __('general.destination_warehouse'),
            'project_id' => __('general.project'),
            'cost_center_id' => __('general.cost_center'),
            'quantity' => __('general.quantity'),
            'unit_cost' => __('general.unit_cost'),
            'note' => __('general.note'),
        ];
    }

    public function submit(): void
    {
        $business = $this->requireBusiness();
        $validated = $this->validate();

        if ($this->mode === 'allocate' && empty($validated['project_id']) && empty($validated['cost_center_id'])) {
            throw ValidationException::withMessages([
                'project_id' => [__('general.stock_allocation_dimension_required')],
            ]);
        }

        /** @var Product $product */
        $product = Product::query()->findOrFail($validated['product_id']);
        $quantity = (string) $validated['quantity'];
        $unitCost = (string) ($validated['unit_cost'] ?? '0');
        $note = filled($validated['note'] ?? null) ? (string) $validated['note'] : null;
        $userId = Auth::id();

        match ($this->mode) {
            'receive' => app(AdjustStockAction::class)->handle(
                business: $business,
                product: $product,
                quantityDelta: $quantity,
                movementType: StockMovementType::Purchase,
                warehouseId: (int) $validated['warehouse_id'],
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
            ),
            'issue' => app(AdjustStockAction::class)->handle(
                business: $business,
                product: $product,
                quantityDelta: bcmul($quantity, '-1', 18),
                movementType: StockMovementType::Sale,
                warehouseId: (int) $validated['warehouse_id'],
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
            ),
            'adjust' => app(AdjustStockAction::class)->handle(
                business: $business,
                product: $product,
                quantityDelta: (string) $validated['quantity'],
                movementType: StockMovementType::Adjustment,
                warehouseId: (int) $validated['warehouse_id'],
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
                allowNegative: true,
            ),
            'transfer' => app(TransferStockAction::class)->handle(
                business: $business,
                product: $product,
                sourceWarehouseId: (int) $validated['source_warehouse_id'],
                destinationWarehouseId: (int) $validated['destination_warehouse_id'],
                quantity: $quantity,
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
            ),
            'allocate' => app(AllocateStockAction::class)->handle(
                business: $business,
                product: $product,
                quantity: $quantity,
                projectId: isset($validated['project_id']) ? (int) $validated['project_id'] : null,
                costCenterId: isset($validated['cost_center_id']) ? (int) $validated['cost_center_id'] : null,
                sourceWarehouseId: isset($validated['source_warehouse_id']) ? (int) $validated['source_warehouse_id'] : null,
                destinationWarehouseId: isset($validated['destination_warehouse_id']) ? (int) $validated['destination_warehouse_id'] : null,
                consume: (bool) ($validated['consume'] ?? false),
                unitCost: $unitCost,
                note: $note,
                userId: $userId,
            ),
            default => throw ValidationException::withMessages([
                'mode' => [__('general.invalid_stock_operation')],
            ]),
        };

        $this->resetFormState();
    }

    public function resetForMode(string $mode): void
    {
        $this->resetFormState();
        $this->mode = $mode;
    }

    protected function requireBusiness(): Business
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'product_id' => [__('general.business_required')],
            ]);
        }

        return Business::query()->findOrFail($businessId);
    }

    protected function resetFormState(): void
    {
        $this->product_id = null;
        $this->warehouse_id = null;
        $this->source_warehouse_id = null;
        $this->destination_warehouse_id = null;
        $this->project_id = null;
        $this->cost_center_id = null;
        $this->quantity = '';
        $this->unit_cost = '0';
        $this->note = '';
        $this->consume = false;
    }
}
