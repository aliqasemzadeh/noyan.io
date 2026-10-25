<?php

namespace App\Livewire\Forms;

use App\Models\Accounting\SettlementType;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ActivateBusinessSettlementTypeForm extends Form
{
    public ?int $settlement_type_id = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $activatedIds = $businessId
            ? Business::query()->find($businessId)?->businessSettlementTypes()->pluck('settlement_type_id')->all() ?? []
            : [];

        $availableIds = $businessId === null
            ? []
            : SettlementType::query()
                ->availableToBusiness($businessId)
                ->whereNotIn('id', $activatedIds)
                ->pluck('id')
                ->all();

        return [
            'settlement_type_id' => ['required', 'integer', Rule::in($availableIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'settlement_type_id' => __('general.settlement_type'),
        ];
    }

    public function store(): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'settlement_type_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $business = Business::query()->findOrFail($businessId);
        $settlementType = SettlementType::query()->findOrFail($validated['settlement_type_id']);

        $business->activateSettlementType($settlementType);

        $this->reset();
    }
}
