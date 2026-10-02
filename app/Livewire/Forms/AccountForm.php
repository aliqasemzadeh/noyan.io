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

    public string $account_number = '';

    public string $note = '';

    public string $opening_balance = '0';

    public bool $is_active = true;

    public function setModel(Account $account): void
    {
        $this->account = $account;
        $this->name = $account->name;
        $this->currency_id = $account->currency_id;
        $this->account_number = $account->account_number ?? '';
        $this->note = $account->note ?? '';
        $this->opening_balance = rtrim(rtrim((string) $account->opening_balance, '0'), '.') ?: '0';
        $this->is_active = $account->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $decimalPlaces = $this->selectedCurrency()?->decimal_places ?? 18;
        $allowedCurrencyIds = $this->allowedCurrencyIds();

        $openingBalanceRule = $decimalPlaces === 0
            ? ['required', 'string', 'regex:/^-?\d+$/']
            : ['required', 'string', 'regex:/^-?\d+(\.\d{1,'.$decimalPlaces.'})?$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => [
                'required',
                'integer',
                Rule::in($allowedCurrencyIds),
            ],
            'account_number' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'opening_balance' => $openingBalanceRule,
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
            'account_number' => __('general.account_number'),
            'note' => __('general.note'),
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
        $validated['account_number'] = $validated['account_number'] !== '' ? $validated['account_number'] : null;
        $validated['note'] = $validated['note'] !== '' ? $validated['note'] : null;
        $validated['opening_balance'] = $this->normalizeBalance((string) $validated['opening_balance']);

        $account = Account::create($validated);

        $this->reset();

        return $account;
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated['account_number'] = $validated['account_number'] !== '' ? $validated['account_number'] : null;
        $validated['note'] = $validated['note'] !== '' ? $validated['note'] : null;
        $validated['opening_balance'] = $this->normalizeBalance((string) $validated['opening_balance']);

        $this->account->update($validated);

        $this->reset();
    }

    /**
     * @return list<int>
     */
    protected function allowedCurrencyIds(): array
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return [];
        }

        $ids = Currency::cachedForBusiness($businessId)->pluck('id')->all();

        if ($this->account?->currency_id && ! in_array($this->account->currency_id, $ids, true)) {
            $ids[] = $this->account->currency_id;
        }

        return $ids;
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
