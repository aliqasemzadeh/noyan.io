<?php

namespace App\Livewire\Forms;

use App\Models\Accounting\BusinessCurrency;
use Livewire\Form;

class BusinessCurrencyForm extends Form
{
    public ?BusinessCurrency $businessCurrency = null;

    public string $exchange_rate_to_base = '1';

    public bool $is_base = false;

    public function setModel(BusinessCurrency $businessCurrency): void
    {
        $this->businessCurrency = $businessCurrency;
        $this->exchange_rate_to_base = rtrim(rtrim((string) $businessCurrency->exchange_rate_to_base, '0'), '.') ?: '1';
        $this->is_base = $businessCurrency->is_base;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->is_base) {
            return [
                'is_base' => ['boolean'],
                'exchange_rate_to_base' => ['nullable'],
            ];
        }

        return [
            'is_base' => ['boolean'],
            'exchange_rate_to_base' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'exchange_rate_to_base' => __('general.exchange_rate_to_base'),
            'is_base' => __('general.base_currency'),
        ];
    }

    public function update(): void
    {
        $validated = $this->validate();

        if ($validated['is_base']) {
            $this->businessCurrency->setAsBase();

            return;
        }

        $this->businessCurrency->update([
            'is_base' => false,
            'exchange_rate_to_base' => $this->normalizeRate((string) $validated['exchange_rate_to_base']),
        ]);

        BusinessCurrency::forgetCache($this->businessCurrency->business_id);
    }

    protected function normalizeRate(string $rate): string
    {
        if (! str_contains($rate, '.')) {
            return $rate;
        }

        return rtrim(rtrim($rate, '0'), '.') ?: '0';
    }
}
