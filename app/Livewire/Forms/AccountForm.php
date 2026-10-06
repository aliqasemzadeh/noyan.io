<?php

namespace App\Livewire\Forms;

use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Models\Accounting\Account;
use App\Models\Currency;
use App\Support\AccountValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class AccountForm extends Form
{
    public ?Account $account = null;

    public string $name = '';

    public ?int $currency_id = null;

    public string $sub_type = AccountSubType::Cash->value;

    public string $bank_name = '';

    public string $account_number = '';

    public string $card_number = '';

    public string $iban = '';

    public string $note = '';

    public string $opening_balance = '0';

    public bool $is_active = true;

    public function setModel(Account $account): void
    {
        $this->account = $account;
        $this->name = $account->name;
        $this->currency_id = $account->currency_id;
        $this->sub_type = $account->sub_type->value;
        $this->bank_name = $account->bank_name ?? '';
        $this->account_number = $account->account_number ?? '';
        $this->card_number = $account->card_number ?? '';
        $this->iban = $account->iban ?? '';
        $this->note = $account->note ?? '';
        $this->opening_balance = rtrim(rtrim((string) $account->opening_balance, '0'), '.') ?: '0';
        $this->is_active = $account->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AccountValidationRules::rules(
            $this->allowedCurrencyIds(),
            $this->selectedCurrency(),
            $this->card_number,
            $this->iban,
        );
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'currency_id' => __('general.currency'),
            'sub_type' => __('general.account_sub_type'),
            'bank_name' => __('general.bank_name'),
            'account_number' => __('general.account_number'),
            'card_number' => __('general.card_number'),
            'iban' => __('general.iban'),
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
        $validated = $this->normalizeOptionalStrings($validated);
        $validated['opening_balance'] = AccountValidationRules::normalizeBalance((string) $validated['opening_balance']);

        $account = Account::createForBusiness((int) $businessId, $validated);

        $this->reset();
        $this->sub_type = AccountSubType::Cash->value;
        $this->opening_balance = '0';
        $this->is_active = true;

        return $account;
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated['type'] = AccountType::Asset;
        $validated = $this->normalizeOptionalStrings($validated);
        $validated['opening_balance'] = AccountValidationRules::normalizeBalance((string) $validated['opening_balance']);

        $previousOpening = AccountValidationRules::normalizeBalance((string) $this->account->opening_balance);
        $openingDelta = bcsub($validated['opening_balance'], $previousOpening, 18);
        $validated['current_balance'] = AccountValidationRules::normalizeBalance(
            bcadd((string) $this->account->current_balance, $openingDelta, 18)
        );

        $this->account->update($validated);

        Account::forgetBalanceCache((int) $this->account->business_id, (int) $this->account->id);
        Account::forgetOptionsCache((int) $this->account->business_id);

        $this->reset();
        $this->sub_type = AccountSubType::Cash->value;
        $this->opening_balance = '0';
        $this->is_active = true;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeOptionalStrings(array $validated): array
    {
        foreach (['bank_name', 'account_number', 'card_number', 'iban', 'note'] as $field) {
            $validated[$field] = ($validated[$field] ?? '') !== '' ? $validated[$field] : null;
        }

        return $validated;
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
}
