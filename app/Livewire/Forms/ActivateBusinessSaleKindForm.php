<?php

namespace App\Livewire\Forms;

use App\Models\Accounting\SaleKind;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ActivateBusinessSaleKindForm extends Form
{
    public ?int $sale_kind_id = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $activatedIds = $businessId
            ? Business::query()->find($businessId)?->businessSaleKinds()->pluck('sale_kind_id')->all() ?? []
            : [];

        $availableIds = $businessId === null
            ? []
            : SaleKind::query()
                ->availableToBusiness($businessId)
                ->whereNotIn('id', $activatedIds)
                ->pluck('id')
                ->all();

        return [
            'sale_kind_id' => ['required', 'integer', Rule::in($availableIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'sale_kind_id' => __('general.sale_kind'),
        ];
    }

    public function store(): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'sale_kind_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $business = Business::query()->findOrFail($businessId);
        $saleKind = SaleKind::query()->findOrFail($validated['sale_kind_id']);

        $business->activateSaleKind($saleKind);

        $this->reset();
    }
}
