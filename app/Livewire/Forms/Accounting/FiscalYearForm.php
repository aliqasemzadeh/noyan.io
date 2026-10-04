<?php

namespace App\Livewire\Forms\Accounting;

use App\Models\Accounting\FiscalYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class FiscalYearForm extends Form
{
    public ?FiscalYear $fiscalYear = null;

    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public bool $is_closed = false;

    public function setModel(FiscalYear $fiscalYear): void
    {
        $this->fiscalYear = $fiscalYear;
        $this->name = $fiscalYear->name;
        $this->start_date = $fiscalYear->start_date->toDateString();
        $this->end_date = $fiscalYear->end_date->toDateString();
        $this->is_closed = $fiscalYear->is_closed;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fiscal_years', 'name')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'))
                    ->ignore($this->fiscalYear?->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_closed' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'start_date' => __('general.fiscal_year_start_date'),
            'end_date' => __('general.fiscal_year_end_date'),
            'is_closed' => __('general.fiscal_year_is_closed'),
        ];
    }

    public function store(): FiscalYear
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['business_id'] = $businessId;

        $fiscalYear = FiscalYear::query()->create($validated);
        $this->resetFormState();

        return $fiscalYear;
    }

    public function update(): void
    {
        $this->requireBusinessId();
        $validated = $this->validate();

        $this->fiscalYear?->update($validated);
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
        $this->is_closed = false;
    }
}
