<?php

namespace App\Livewire\Forms;

use App\Models\Accounting\Account;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class AccountForm extends Form
{
    public ?Account $account = null;

    public string $name = '';

    public ?int $currency_id = null;

    public string $opening_balance = '0';

    public bool $is_active = true;

    public function setModel(Account $account): void
    {
        $this->account = $account;
        $this->name = $account->name;
        $this->currency_id = $account->currency_id;
        $this->opening_balance = rtrim(rtrim((string) $account->opening_balance, '0'), '.') ?: '0';
        $this->is_active = $account->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $decimalPlaces = $this->selectedCurrency()?->decimal_places ?? 18;
        $allowedCurrencyIds = Currency::query()
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->where('is_active', true);

                if ($this->account?->currency_id) {
                    $query->orWhere('id', $this->account->currency_id);
                }
            })
            ->pluck('id')
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => [
                'required',
                'integer',
                Rule::in($allowedCurrencyIds),
            ],
            'opening_balance' => [
                'required',
                'string',
                'regex:/^-?\d+(\.\d{1,'.$decimalPlaces.'})?$/',
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'currency_id' => __('general.currency'),
            'opening_balance' => __('general.opening_balance'),
            'is_active' => __('general.is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $decimalPlaces = $this->selectedCurrency()?->decimal_places ?? 18;

        return [
            'opening_balance.regex' => __('general.opening_balance_decimal_places', [
                'places' => $decimalPlaces,
            ]),
        ];
    }

    public function store(): Account
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $validated['business_id'] = $businessId;
        $validated['opening_balance'] = $this->normalizeBalance((string) $validated['opening_balance']);

        $account = Account::create($validated);

        $this->reset();

        return $account;
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated['opening_balance'] = $this->normalizeBalance((string) $validated['opening_balance']);

        $this->account->update($validated);

        $this->reset();
    }

    protected function selectedCurrency(): ?Currency
    {
        if ($this->currency_id === null) {
            return null;
        }

        return Currency::query()->find($this->currency_id);
    }

    protected function normalizeBalance(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
