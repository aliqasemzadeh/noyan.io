<?php

namespace App\Livewire\Forms\Accounting;

use App\Models\Accounting\CostCenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class CostCenterForm extends Form
{
    public ?CostCenter $costCenter = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public function setModel(CostCenter $costCenter): void
    {
        $this->costCenter = $costCenter;
        $this->code = $costCenter->code ?? '';
        $this->name = $costCenter->name;
        $this->is_active = $costCenter->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'code' => __('general.code'),
            'name' => __('general.name'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): CostCenter
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['business_id'] = $businessId;
        $validated['code'] = filled($validated['code'] ?? null) ? $validated['code'] : null;

        $costCenter = CostCenter::query()->create($validated);
        CostCenter::forgetOptionsCache($businessId);
        $this->resetFormState();

        return $costCenter;
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['code'] = filled($validated['code'] ?? null) ? $validated['code'] : null;

        $this->costCenter?->update($validated);
        CostCenter::forgetOptionsCache($businessId);
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
        $this->reset();
        $this->is_active = true;
    }
}
