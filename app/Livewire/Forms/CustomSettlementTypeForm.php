<?php

namespace App\Livewire\Forms;

use App\Models\Accounting\SettlementType;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class CustomSettlementTypeForm extends Form
{
    public string $name = '';

    public string $code = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                'alpha_dash:ascii',
                Rule::unique('settlement_types', 'code')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'code' => __('general.settlement_type_code'),
        ];
    }

    public function store(): SettlementType
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $business = Business::query()->findOrFail($businessId);

        $code = filled($validated['code'] ?? null)
            ? strtolower((string) $validated['code'])
            : Str::slug($validated['name']);

        if ($code === '') {
            $code = 'custom-'.Str::lower(Str::random(6));
        }

        $settlementType = SettlementType::query()->create([
            'code' => $code,
            'name' => $validated['name'],
            'is_system' => false,
            'business_id' => $businessId,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        $business->activateSettlementType($settlementType);

        $this->reset();

        return $settlementType;
    }
}
