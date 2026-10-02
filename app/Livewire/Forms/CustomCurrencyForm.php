<?php

namespace App\Livewire\Forms;

use App\Enums\CurrencyType;
use App\Models\Business;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class CustomCurrencyForm extends Form
{
    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public int $decimal_places = 2;

    public string $exchange_rate_to_base = '1';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'code' => [
                'required',
                'string',
                'max:16',
                'alpha_dash:ascii',
                Rule::unique('currencies', 'code')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
                Rule::notIn(
                    Currency::query()->system()->pluck('code')->map(fn (string $code): string => strtoupper($code))->all(),
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:18'],
            'exchange_rate_to_base' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
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
            'decimal_places' => __('general.decimal_places'),
            'exchange_rate_to_base' => __('general.exchange_rate_to_base'),
        ];
    }

    public function store(): Currency
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'code' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $business = Business::query()->findOrFail($businessId);

        $currency = Currency::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'symbol' => $validated['symbol'] !== '' ? $validated['symbol'] : null,
            'type' => CurrencyType::Custom,
            'is_system' => false,
            'business_id' => $businessId,
            'decimal_places' => $validated['decimal_places'],
            'is_active' => true,
        ]);

        $business->activateCurrency(
            $currency,
            $this->normalizeRate((string) $validated['exchange_rate_to_base']),
        );

        $this->reset();

        return $currency;
    }

    protected function normalizeRate(string $rate): string
    {
        if (! str_contains($rate, '.')) {
            return $rate;
        }

        return rtrim(rtrim($rate, '0'), '.') ?: '0';
    }
}
