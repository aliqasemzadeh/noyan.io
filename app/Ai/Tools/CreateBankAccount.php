<?php

namespace App\Ai\Tools;

use App\Enums\AccountSubType;
use App\Models\Accounting\Account;
use App\Models\Business;
use App\Models\Currency;
use App\Support\AccountValidationRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateBankAccount implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'ایجاد حساب بانکی جدید فقط وقتی کاربر صریحاً ساخت حساب خواسته و شماره حساب هنوز وجود ندارد. اگر پیامک برداشت/واریز دارد یا شماره حساب موجود است، به‌جای این ابزار از create_transaction استفاده کن.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        $businessId = $user?->current_business_id;

        if ($user === null || $businessId === null) {
            return __('general.business_required');
        }

        $name = trim((string) $request->string('name'));
        $bankName = trim((string) $request->string('bank_name'));
        $accountNumber = $this->normalizeDigits((string) $request->string('account_number'));
        $balance = $this->normalizeDigits((string) $request->string('balance'));
        $currencyCode = strtoupper(trim((string) $request->string('currency_code')));

        $missing = [];
        if ($name === '') {
            $missing[] = __('general.name');
        }
        if ($bankName === '') {
            $missing[] = __('general.bank_name');
        }

        if ($missing !== []) {
            return __('general.ai_account_missing_fields', [
                'fields' => implode('، ', $missing),
            ]);
        }

        if ($accountNumber === '') {
            return __('general.ai_account_missing_fields', [
                'fields' => __('general.account_number'),
            ]);
        }

        $duplicate = Account::query()
            ->where('business_id', $businessId)
            ->where('account_number', $accountNumber)
            ->exists();

        if ($duplicate) {
            return __('general.ai_account_already_exists', [
                'number' => $accountNumber,
            ]);
        }

        $currency = $this->resolveCurrency((int) $businessId, $currencyCode !== '' ? $currencyCode : null);

        if ($currency === null) {
            return __('general.ai_account_currency_missing');
        }

        $payload = [
            'name' => $name,
            'currency_id' => $currency->id,
            'sub_type' => AccountSubType::Bank->value,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'card_number' => null,
            'iban' => null,
            'note' => null,
            'opening_balance' => AccountValidationRules::normalizeBalance($balance),
            'is_active' => true,
        ];

        $validator = Validator::make(
            $payload,
            AccountValidationRules::rules(
                Currency::cachedForBusiness((int) $businessId)->pluck('id')->all(),
                $currency,
            ),
            attributes: [
                'name' => __('general.name'),
                'currency_id' => __('general.currency'),
                'sub_type' => __('general.account_sub_type'),
                'bank_name' => __('general.bank_name'),
                'account_number' => __('general.account_number'),
                'opening_balance' => __('general.opening_balance'),
            ],
        );

        if ($validator->fails()) {
            return __('general.ai_account_invalid', [
                'errors' => $validator->errors()->first(),
            ]);
        }

        $account = Account::createForBusiness((int) $businessId, $validator->validated());

        return __('general.ai_account_created', [
            'name' => $account->name,
            'bank' => $account->bank_name,
            'number' => $account->account_number,
            'balance' => AccountValidationRules::normalizeBalance((string) $account->opening_balance),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('نام نمایشی حساب، مثل حساب جاری ملت. هرگز حدس نزن.')
                ->required(),
            'bank_name' => $schema->string()
                ->description('نام بانک، مثل ملت یا ملی. هرگز حدس نزن.')
                ->required(),
            'account_number' => $schema->string()
                ->description('شماره حساب بانکی (ارقام بعد از کلمه حساب در پیامک)')
                ->required(),
            'balance' => $schema->string()
                ->description('مانده حساب بعد از کلمه مانده؛ کاما مجاز است و حذف می‌شود')
                ->required(),
            'currency_code' => $schema->string()
                ->description('کد ارز مثل IRR؛ در صورت نبود از ارز پایه کسب‌وکار استفاده می‌شود'),
        ];
    }

    protected function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);

        return preg_replace('/[,\s،٬]/u', '', $value) ?? '';
    }

    protected function resolveCurrency(int $businessId, ?string $currencyCode): ?Currency
    {
        $currencies = Currency::cachedForBusiness($businessId);

        if ($currencies->isEmpty()) {
            return null;
        }

        if ($currencyCode !== null) {
            return $currencies->firstWhere('code', $currencyCode);
        }

        $baseCurrencyId = Business::query()
            ->find($businessId)
            ?->baseBusinessCurrency()
            ?->currency_id;

        if ($baseCurrencyId !== null) {
            $base = $currencies->firstWhere('id', $baseCurrencyId);

            if ($base !== null) {
                return $base;
            }
        }

        return $currencies->first();
    }
}
