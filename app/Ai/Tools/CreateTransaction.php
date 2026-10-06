<?php

namespace App\Ai\Tools;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Support\LocaleDate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTransaction implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'ثبت تراکنش درآمد یا هزینه روی یک حساب موجود از روی پیامک بانکی. هرگز حساب بانکی جدید نساز. فقط وقتی حساب موجود مشخص است فراخوانی شود.';
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

        $typeValue = strtolower(trim((string) $request->string('type')));
        $amount = $this->normalizeDigits((string) $request->string('amount'));
        $accountNumber = $this->normalizeDigits((string) $request->string('account_number'));
        $accountName = trim((string) $request->string('account_name'));
        $transactionDateInput = trim((string) $request->string('transaction_date'));
        $note = trim((string) $request->string('note'));
        $referenceNumber = trim((string) $request->string('reference_number'));

        $type = TransactionType::tryFrom($typeValue);

        if ($type === null || ! in_array($type, [TransactionType::Income, TransactionType::Expense], true)) {
            return __('general.ai_transaction_invalid', [
                'errors' => __('general.transaction_type'),
            ]);
        }

        if ($amount === '' || ! is_numeric($amount) || bccomp($amount, '0', 18) !== 1) {
            return __('general.ai_transaction_missing_fields', [
                'fields' => __('general.amount'),
            ]);
        }

        $account = $this->resolveAccount((int) $businessId, $accountNumber, $accountName);

        if ($account === null) {
            return __('general.ai_transaction_account_not_found');
        }

        $storageDate = $transactionDateInput !== ''
            ? LocaleDate::toStorageDate($transactionDateInput)
            : now()->toDateString();

        if ($storageDate === null) {
            return __('general.ai_transaction_invalid', [
                'errors' => __('general.transaction_date'),
            ]);
        }

        $exchangeRate = BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->where('currency_id', $account->currency_id)
            ->value('exchange_rate_to_base') ?? '1';

        try {
            $transaction = app(ProcessTransactionAction::class)->handle($user, [
                'type' => $type,
                'account_id' => (int) $account->id,
                'transaction_date' => $storageDate,
                'currency' => $account->currency?->code ?? 'IRR',
                'exchange_rate' => (string) $exchangeRate,
                'amount' => $amount,
                'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                'note' => $note !== '' ? $note : null,
            ]);
        } catch (ValidationException $exception) {
            return __('general.ai_transaction_invalid', [
                'errors' => $exception->validator->errors()->first() ?: __('general.ai_error'),
            ]);
        }

        return __('general.ai_transaction_created', [
            'type' => $type->label(),
            'amount' => $amount,
            'account' => $account->name,
            'date' => LocaleDate::formatDate($transaction->transaction_date),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('نوع تراکنش: income برای واریز، expense برای برداشت')
                ->required(),
            'amount' => $schema->string()
                ->description('مبلغ برداشت یا واریز؛ کاما و ارقام فارسی مجاز است')
                ->required(),
            'account_number' => $schema->string()
                ->description('شماره حساب بانکی موجود (ارقام بعد از کلمه حساب در پیامک)'),
            'account_name' => $schema->string()
                ->description('نام نمایشی حساب موجود اگر شماره مشخص نیست'),
            'transaction_date' => $schema->string()
                ->description('تاریخ تراکنش جلالی مثل 1403/07/01 یا میلادی Y-m-d؛ خالی = امروز'),
            'note' => $schema->string()
                ->description('یادداشت اختیاری از متن پیامک'),
            'reference_number' => $schema->string()
                ->description('شماره پیگیری اختیاری'),
        ];
    }

    protected function resolveAccount(int $businessId, string $accountNumber, string $accountName): ?Account
    {
        $query = Account::query()
            ->with('currency')
            ->where('business_id', $businessId)
            ->where('is_active', true);

        if ($accountNumber !== '') {
            $account = (clone $query)->where('account_number', $accountNumber)->first();

            if ($account !== null) {
                return $account;
            }
        }

        if ($accountName !== '') {
            $account = (clone $query)->where('name', $accountName)->first();

            if ($account !== null) {
                return $account;
            }

            return (clone $query)->where('name', 'like', '%'.$accountName.'%')->first();
        }

        return null;
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
}
