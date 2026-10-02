<?php

namespace App\Livewire\Forms;

use App\Models\Business;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ActivateBusinessCurrencyForm extends Form
{
    public ?int $currency_id = null;

    public string $exchange_rate_to_base = '1';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $activatedIds = $businessId
            ? Business::query()->find($businessId)?->businessCurrencies()->pluck('currency_id')->all() ?? []
            : [];

        $availableIds = Currency::query()
            ->system()
            ->active()
            ->whereNotIn('id', $activatedIds)
            ->pluck('id')
            ->all();

        return [
            'currency_id' => ['required', 'integer', Rule::in($availableIds)],
            'exchange_rate_to_base' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'currency_id' => __('general.currency'),
            'exchange_rate_to_base' => __('general.exchange_rate_to_base'),
        ];
    }

    public function store(): void
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'currency_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $business = Business::query()->findOrFail($businessId);
        $currency = Currency::query()->findOrFail($validated['currency_id']);

        $business->activateCurrency(
            $currency,
            $this->normalizeRate((string) $validated['exchange_rate_to_base']),
        );

        $this->reset();
    }

    protected function normalizeRate(string $rate): string
    {
        if (! str_contains($rate, '.')) {
            return $rate;
        }

        return rtrim(rtrim($rate, '0'), '.') ?: '0';
    }
}
