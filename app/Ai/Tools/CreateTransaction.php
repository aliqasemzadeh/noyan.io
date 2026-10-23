<?php

namespace App\Ai\Tools;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Models\User;
use App\Support\Accounting\ShetabTransferFee;
use App\Support\LocaleDate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        return 'پیش‌نمایش یا ثبت یک یا چند تراکنش از پیامک بانکی (برداشت/واریز) روی حساب موجود. ابتدا با confirmed=false اعتبارسنجی و پیش‌نمایش بگیر؛ فقط بعد از تأیید صریح کاربر با confirmed=true ثبت کن. اگر چند پیامک در پیام هست همه را در transactions بفرست. همیشه مانده پیامک را در expected_balance بفرست. هرگز حساب بانکی جدید نساز.';
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

        $confirmed = $request->boolean('confirmed');
        $transactions = $request->array('transactions');

        if ($transactions !== []) {
            return $this->handleBatch($user, (int) $businessId, $transactions, $confirmed);
        }

        $result = $this->createOne($user, (int) $businessId, [
            'type' => (string) $request->string('type'),
            'amount' => (string) $request->string('amount'),
            'account_number' => (string) $request->string('account_number'),
            'account_name' => (string) $request->string('account_name'),
            'transaction_date' => (string) $request->string('transaction_date'),
            'note' => (string) $request->string('note'),
            'reference_number' => (string) $request->string('reference_number'),
            'expected_balance' => (string) $request->string('expected_balance'),
        ], $confirmed);

        if (! $confirmed && $result['ok']) {
            return $result['message']."\n".__('general.ai_transaction_preview_ask_confirm');
        }

        return $result['message'];
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        $itemFields = fn (JsonSchema $schema): array => [
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
            'expected_balance' => $schema->string()
                ->description('مانده حساب بعد از تراکنش از پیامک بانکی؛ برای جلوگیری از ثبت در کسب‌وکار اشتباه الزامی است وقتی مانده در پیامک هست'),
        ];

        return [
            'confirmed' => $schema->boolean()
                ->description('false یا خالی = فقط پیش‌نمایش و چک مانده بدون ثبت؛ true = ثبت واقعی فقط بعد از تأیید کاربر'),
            'transactions' => $schema->array()
                ->items($schema->object($itemFields))
                ->description('وقتی چند پیامک یا چند تراکنش در یک پیام هست همه را اینجا بفرست (یک فراخوانی)'),
            'type' => $schema->string()
                ->description('نوع تراکنش تک‌تایی: income یا expense'),
            'amount' => $schema->string()
                ->description('مبلغ تراکنش تک‌تایی'),
            'account_number' => $schema->string()
                ->description('شماره حساب بانکی موجود'),
            'account_name' => $schema->string()
                ->description('نام نمایشی حساب موجود'),
            'transaction_date' => $schema->string()
                ->description('تاریخ تراکنش؛ خالی = امروز'),
            'note' => $schema->string()
                ->description('یادداشت اختیاری'),
            'reference_number' => $schema->string()
                ->description('شماره پیگیری اختیاری'),
            'expected_balance' => $schema->string()
                ->description('مانده حساب بعد از تراکنش از پیامک بانکی'),
        ];
    }

    /**
     * @param  list<mixed>  $transactions
     */
    protected function handleBatch(User $user, int $businessId, array $transactions, bool $confirmed): string
    {
        $lines = [];
        $okCount = 0;
        $index = 0;
        /** @var array<int, string> $runningBalances */
        $runningBalances = [];

        foreach ($transactions as $item) {
            $index++;

            if (! is_array($item)) {
                $lines[] = __('general.ai_transaction_batch_item_failed', [
                    'n' => $index,
                    'error' => __('general.ai_transaction_invalid', [
                        'errors' => __('general.ai_error'),
                    ]),
                ]);

                continue;
            }

            $result = $this->createOne($user, $businessId, [
                'type' => (string) ($item['type'] ?? ''),
                'amount' => (string) ($item['amount'] ?? ''),
                'account_number' => (string) ($item['account_number'] ?? ''),
                'account_name' => (string) ($item['account_name'] ?? ''),
                'transaction_date' => (string) ($item['transaction_date'] ?? ''),
                'note' => (string) ($item['note'] ?? ''),
                'reference_number' => (string) ($item['reference_number'] ?? ''),
                'expected_balance' => (string) ($item['expected_balance'] ?? ''),
            ], $confirmed, $runningBalances);

            if ($result['ok']) {
                $okCount++;
                $lines[] = __('general.ai_transaction_batch_item_ok', [
                    'n' => $index,
                    'message' => $result['message'],
                ]);
            } else {
                $lines[] = __('general.ai_transaction_batch_item_failed', [
                    'n' => $index,
                    'error' => $result['message'],
                ]);
            }
        }

        if ($index === 0) {
            return __('general.ai_transaction_batch_empty');
        }

        $summaryKey = $confirmed
            ? 'general.ai_transaction_batch_summary'
            : 'general.ai_transaction_batch_preview_summary';

        $summary = __($summaryKey, [
            'created' => $okCount,
            'ready' => $okCount,
            'total' => $index,
            'items' => implode("\n", $lines),
        ]);

        if (! $confirmed && $okCount > 0) {
            $summary .= "\n".__('general.ai_transaction_preview_ask_confirm');
        }

        return $summary;
    }

    /**
     * @param  array{
     *     type: string,
     *     amount: string,
     *     account_number: string,
     *     account_name: string,
     *     transaction_date: string,
     *     note: string,
     *     reference_number: string,
     *     expected_balance: string
     * }  $data
     * @param  array<int, string>  $runningBalances
     * @return array{ok: bool, message: string}
     */
    protected function createOne(
        User $user,
        int $businessId,
        array $data,
        bool $confirmed,
        array &$runningBalances = [],
    ): array {
        $typeValue = strtolower(trim($data['type']));
        $amount = $this->normalizeDigits($data['amount']);
        $accountNumber = $this->normalizeDigits($data['account_number']);
        $accountName = trim($data['account_name']);
        $transactionDateInput = trim($data['transaction_date']);
        $note = trim($data['note']);
        $referenceNumber = trim($data['reference_number']);
        $expectedBalance = $this->normalizeDigits($data['expected_balance']);

        $type = TransactionType::tryFrom($typeValue);

        if ($type === null || ! in_array($type, [TransactionType::Income, TransactionType::Expense], true)) {
            return $this->fail(__('general.ai_transaction_invalid', [
                'errors' => __('general.transaction_type'),
            ]));
        }

        if ($amount === '' || ! is_numeric($amount) || bccomp($amount, '0', 18) !== 1) {
            return $this->fail(__('general.ai_transaction_missing_fields', [
                'fields' => __('general.amount'),
            ]));
        }

        $account = $this->resolveAccount($businessId, $accountNumber, $accountName);

        if ($account === null) {
            return $this->fail(__('general.ai_transaction_account_not_found'));
        }

        $storageDate = $transactionDateInput !== ''
            ? LocaleDate::toStorageDate($transactionDateInput)
            : now()->toDateString();

        if ($storageDate === null) {
            return $this->fail(__('general.ai_transaction_invalid', [
                'errors' => __('general.transaction_date'),
            ]));
        }

        $currencyCode = $account->currency?->code ?? 'IRR';
        $accountId = (int) $account->id;
        $currentBalance = $runningBalances[$accountId]
            ?? (string) ($account->current_balance ?? '0');
        $feeAmount = null;
        $projected = $this->projectBalance($type, $currentBalance, $amount);

        if ($expectedBalance !== '') {
            if (! is_numeric($expectedBalance)) {
                return $this->fail(__('general.ai_transaction_invalid', [
                    'errors' => __('general.current_balance'),
                ]));
            }

            if (bccomp($projected, $expectedBalance, 18) !== 0) {
                $feeAmount = $type === TransactionType::Expense
                    ? ShetabTransferFee::forAmount($amount, $currencyCode)
                    : null;

                if ($feeAmount === null) {
                    return $this->fail($this->balanceMismatchMessage(
                        $user,
                        $account,
                        $currentBalance,
                        $expectedBalance,
                        $projected,
                    ));
                }

                $projectedWithFee = $this->projectBalance(
                    $type,
                    $currentBalance,
                    bcadd($amount, $feeAmount, 18),
                );

                if (bccomp($projectedWithFee, $expectedBalance, 18) !== 0) {
                    return $this->fail($this->balanceMismatchMessage(
                        $user,
                        $account,
                        $currentBalance,
                        $expectedBalance,
                        $projected,
                    ));
                }

                $projected = $projectedWithFee;
            }
        }

        $debitTotal = $feeAmount !== null
            ? bcadd($amount, $feeAmount, 18)
            : $amount;

        if (! $confirmed) {
            $runningBalances[$accountId] = $projected;

            return $this->ok(__('general.ai_transaction_preview_ok', [
                'type' => $type->label(),
                'amount' => $this->formatAmount($amount),
                'account' => $account->name,
                'current' => $this->formatAmount($currentBalance),
                'projected' => $this->formatAmount($projected),
                'expected' => $expectedBalance !== ''
                    ? $this->formatAmount($expectedBalance)
                    : '—',
                'fee' => $feeAmount !== null
                    ? $this->formatAmount($feeAmount)
                    : '—',
                'date' => LocaleDate::formatDate($storageDate),
            ]));
        }

        $exchangeRate = BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->where('currency_id', $account->currency_id)
            ->value('exchange_rate_to_base') ?? '1';

        $payloadBase = [
            'account_id' => $accountId,
            'transaction_date' => $storageDate,
            'currency' => $currencyCode,
            'exchange_rate' => (string) $exchangeRate,
            'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
        ];

        try {
            if ($feeAmount !== null) {
                $transaction = DB::transaction(function () use ($user, $type, $amount, $feeAmount, $note, $payloadBase) {
                    $main = app(ProcessTransactionAction::class)->handle($user, [
                        ...$payloadBase,
                        'type' => $type,
                        'amount' => $amount,
                        'note' => $note !== '' ? $note : null,
                    ]);

                    app(ProcessTransactionAction::class)->handle($user, [
                        ...$payloadBase,
                        'type' => TransactionType::Expense,
                        'amount' => $feeAmount,
                        'note' => __('general.ai_transaction_fee_note'),
                    ]);

                    return $main;
                });

                $runningBalances[$accountId] = $projected;

                return $this->ok(__('general.ai_transaction_created_with_fee', [
                    'type' => $type->label(),
                    'amount' => $amount,
                    'fee' => $feeAmount,
                    'account' => $account->name,
                    'date' => LocaleDate::formatDate($transaction->transaction_date),
                ]));
            }

            $transaction = app(ProcessTransactionAction::class)->handle($user, [
                ...$payloadBase,
                'type' => $type,
                'amount' => $amount,
                'note' => $note !== '' ? $note : null,
            ]);
        } catch (ValidationException $exception) {
            return $this->fail(__('general.ai_transaction_invalid', [
                'errors' => $exception->validator->errors()->first() ?: __('general.ai_error'),
            ]));
        }

        $runningBalances[$accountId] = $this->projectBalance($type, $currentBalance, $debitTotal);

        return $this->ok(__('general.ai_transaction_created', [
            'type' => $type->label(),
            'amount' => $amount,
            'account' => $account->name,
            'date' => LocaleDate::formatDate($transaction->transaction_date),
        ]));
    }

    /**
     * @return array{ok: true, message: string}
     */
    protected function ok(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }

    /**
     * @return array{ok: false, message: string}
     */
    protected function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    protected function projectBalance(TransactionType $type, string $currentBalance, string $amount): string
    {
        return match ($type) {
            TransactionType::Income => bcadd($currentBalance, $amount, 18),
            TransactionType::Expense => bcsub($currentBalance, $amount, 18),
            default => $currentBalance,
        };
    }

    protected function balanceMismatchMessage(
        User $user,
        Account $account,
        string $currentBalance,
        string $expectedBalance,
        string $projectedBalance,
    ): string {
        $businessName = $user->currentBusiness?->name ?: '—';

        return __('general.ai_transaction_balance_mismatch', [
            'account' => $account->name,
            'business' => $businessName,
            'current' => $this->formatAmount($currentBalance),
            'expected' => $this->formatAmount($expectedBalance),
            'projected' => $this->formatAmount($projectedBalance),
        ]);
    }

    protected function formatAmount(string $amount): string
    {
        $normalized = bcadd($amount, '0', 0);

        return number_format((float) $normalized, 0, '.', ',');
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
