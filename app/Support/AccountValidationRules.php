<?php

namespace App\Support;

use App\Enums\AccountSubType;
use App\Models\Currency;
use Illuminate\Validation\Rule;
use Sadegh19b\LaravelPersianValidation\Rules\IranianBankCardNumber;
use Sadegh19b\LaravelPersianValidation\Rules\IranianIban;

class AccountValidationRules
{
    /**
     * @param  list<int>  $allowedCurrencyIds
     * @return array<string, mixed>
     */
    public static function rules(
        array $allowedCurrencyIds,
        ?Currency $currency,
        string $cardNumber = '',
        string $iban = '',
    ): array {
        $decimalPlaces = $currency?->decimal_places ?? 18;

        $openingBalanceRule = $decimalPlaces === 0
            ? ['required', 'string', 'regex:/^-?\d+$/']
            : ['required', 'string', 'regex:/^-?\d+(\.\d{1,'.$decimalPlaces.'})?$/'];

        $cardRules = ['nullable', 'string', 'max:32'];
        if ($cardNumber !== '') {
            $cardRules[] = new IranianBankCardNumber;
        }

        $ibanRules = ['nullable', 'string', 'max:34'];
        if ($iban !== '') {
            $ibanRules[] = new IranianIban;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'currency_id' => [
                'required',
                'integer',
                Rule::in($allowedCurrencyIds),
            ],
            'sub_type' => ['required', Rule::enum(AccountSubType::class)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'card_number' => $cardRules,
            'iban' => $ibanRules,
            'note' => ['nullable', 'string', 'max:2000'],
            'opening_balance' => $openingBalanceRule,
            'is_active' => ['boolean'],
        ];
    }

    public static function normalizeBalance(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }
}
