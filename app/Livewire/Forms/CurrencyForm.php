<?php

namespace App\Livewire\Forms;

use App\Enums\CurrencyType;
use App\Models\Currency;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CurrencyForm extends Form
{
    public ?Currency $currency = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public string $type = CurrencyType::Fiat->value;

    public int $decimal_places = 2;

    public bool $is_active = true;

    public function setModel(Currency $currency): void
    {
        $this->currency = $currency;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = $currency->symbol ?? '';
        $this->type = $currency->type->value;
        $this->decimal_places = $currency->decimal_places;
        $this->is_active = $currency->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:16',
                'alpha_dash:ascii',
                Rule::unique('currencies', 'code')
                    ->whereNull('business_id')
                    ->whereNull('deleted_at')
                    ->ignore($this->currency?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'type' => ['required', Rule::enum(CurrencyType::class)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:18'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'code' => __('general.currency_code'),
            'name' => __('general.name'),
            'symbol' => __('general.currency_symbol'),
            'type' => __('general.currency_type'),
            'decimal_places' => __('general.decimal_places'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Currency
    {
        $validated = $this->validate();
        $validated['code'] = strtoupper($validated['code']);
        $validated['symbol'] = $validated['symbol'] !== '' ? $validated['symbol'] : null;
        $validated['is_system'] = true;
        $validated['business_id'] = null;

        $currency = Currency::create($validated);

        Currency::forgetActiveCache();

        $this->reset();

        return $currency;
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated['code'] = strtoupper($validated['code']);
        $validated['symbol'] = $validated['symbol'] !== '' ? $validated['symbol'] : null;

        $this->currency->update($validated);

        Currency::forgetActiveCache();

        $this->reset();
    }
}
