<?php

namespace App\Livewire\Forms;

use App\Actions\Business\CreateBusinessAction;
use App\Actions\Business\UpdateBusinessAction;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UserBusinessForm extends Form
{
    public ?Business $business = null;

    public string $name = '';

    public string $type = '';

    public string $category = '';

    /**
     * @var list<int|string>
     */
    public array $currency_ids = [];

    public function setModel(Business $business): void
    {
        $this->business = $business;
        $this->name = $business->name;
        $this->type = $business->type?->value ?? '';
        $this->category = $business->category?->value ?? '';
        $this->currency_ids = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(BusinessType::class)],
            'category' => ['required', Rule::enum(BusinessCategory::class)],
        ];

        if ($this->business === null) {
            $rules['currency_ids'] = ['required', 'array', 'min:1'];
            $rules['currency_ids.*'] = [
                'integer',
                Rule::exists('currencies', 'id')->where(function ($query): void {
                    $query->where('is_system', true)
                        ->whereNull('business_id')
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'type' => __('general.business_type'),
            'category' => __('general.business_category'),
            'currency_ids' => __('general.currencies'),
            'currency_ids.*' => __('general.currency'),
        ];
    }

    public function store(User $user, CreateBusinessAction $action): Business
    {
        $validated = $this->validate();

        $business = $action->handle($user, $validated);

        $this->reset();

        return $business;
    }

    public function update(UpdateBusinessAction $action): Business
    {
        $validated = $this->validate();

        $business = $action->handle($this->business, $validated);

        $this->setModel($business);

        return $business;
    }

    public function defaultCurrencyId(): ?int
    {
        return Currency::query()
            ->system()
            ->active()
            ->where('code', 'IRT')
            ->value('id')
            ?? Currency::query()
                ->system()
                ->active()
                ->orderBy('code')
                ->value('id');
    }
}
