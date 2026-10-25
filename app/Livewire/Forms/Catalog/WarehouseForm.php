<?php

namespace App\Livewire\Forms\Catalog;

use App\Actions\Inventory\SetDefaultWarehouseAction;
use App\Enums\Catalog\WarehouseType;
use App\Models\Catalog\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class WarehouseForm extends Form
{
    public ?Warehouse $warehouse = null;

    public string $name = '';

    public string $code = '';

    public string $type = WarehouseType::Main->value;

    public bool $is_default = false;

    public bool $is_sellable = false;

    public bool $is_active = true;

    public function setModel(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse;
        $this->name = $warehouse->name;
        $this->code = $warehouse->code;
        $this->type = $warehouse->type->value;
        $this->is_default = $warehouse->is_default;
        $this->is_sellable = $warehouse->is_sellable;
        $this->is_active = $warehouse->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('warehouses', 'code')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'))
                    ->ignore($this->warehouse?->id),
            ],
            'type' => ['required', Rule::enum(WarehouseType::class)],
            'is_default' => ['boolean'],
            'is_sellable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'code' => __('general.code'),
            'type' => __('general.warehouse_type'),
            'is_default' => __('general.is_default'),
            'is_sellable' => __('general.is_sellable'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Warehouse
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $type = WarehouseType::from($validated['type']);

        $warehouse = Warehouse::query()->create([
            'business_id' => $businessId,
            'name' => $validated['name'],
            'code' => strtoupper((string) $validated['code']),
            'type' => $type,
            'is_default' => false,
            'is_sellable' => (bool) $validated['is_sellable'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        if ($validated['is_default']) {
            app(SetDefaultWarehouseAction::class)->handle($warehouse->business, $warehouse);
            $warehouse->refresh();
        }

        $this->resetFormState();

        return $warehouse;
    }

    public function update(): void
    {
        $this->requireBusinessId();
        $validated = $this->validate();
        $type = WarehouseType::from($validated['type']);

        $this->warehouse->update([
            'name' => $validated['name'],
            'code' => strtoupper((string) $validated['code']),
            'type' => $type,
            'is_sellable' => (bool) $validated['is_sellable'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        if ($validated['is_default']) {
            app(SetDefaultWarehouseAction::class)->handle($this->warehouse->business, $this->warehouse);
        } elseif ($this->warehouse->is_default && ! $validated['is_default']) {
            throw ValidationException::withMessages([
                'is_default' => [__('general.cannot_unset_only_default_warehouse')],
            ]);
        }

        $this->resetFormState();
    }

    protected function requireBusinessId(): int
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => [__('general.business_required')],
            ]);
        }

        return (int) $businessId;
    }

    protected function resetFormState(): void
    {
        $this->warehouse = null;
        $this->name = '';
        $this->code = '';
        $this->type = WarehouseType::Main->value;
        $this->is_default = false;
        $this->is_sellable = false;
        $this->is_active = true;
    }
}
