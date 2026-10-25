<?php

namespace App\Ai\Tools;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Ai\Tools\Concerns\ChecksBusinessPermission;
use App\Enums\Accounting\TransactionType;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\Transaction;
use App\Models\User;
use App\Support\Accounting\ShetabTransferFee;
use App\Support\LocaleDate;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTransaction implements Tool
{
    use ChecksBusinessPermission;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'ثبت یک یا چند تراکنش از پیامک بانکی (برداشت/واریز) روی حساب موجود. اگر مانده پیامک (expected_balance) با زنجیره مانده جور باشد همان لحظه ثبت کن (بدون تأیید). فقط وقتی مانده جور نیست یا مانده در پیامک نیست پیش‌نمایش بده و confirmed=true بخواه. تراکنش‌هایی که مانده‌شان از قبل در دفتر هست را رد کن (skip). اگر چند پیامک هست همه را در transactions بفرست؛ تاریخ را با ساعت پیامک بفرست. هرگز حساب بانکی جدید نساز.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($denied = $this->denyUnlessCan(BusinessPermission::TransactionCreate)) {
            return $denied;
        }

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

        if ($result['action'] === 'preview') {
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
                ->description('تاریخ/ساعت پیامک مثل 05/08/02-16:18 یا 02/08 یا 1405/08/02؛ سال کوتاه را ابزار نزدیک امروز کامل می‌کند؛ خالی = امروز'),
            'note' => $schema->string()
                ->description('یادداشت اختیاری از متن پیامک'),
            'reference_number' => $schema->string()
                ->description('شماره پیگیری اختیاری'),
            'expected_balance' => $schema->string()
                ->description('مانده حساب بعد از تراکنش از پیامک بانکی؛ وقتی هست و جور باشد بدون تأیید ثبت می‌شود'),
        ];

        return [
            'confirmed' => $schema->boolean()
                ->description('فقط وقتی مانده جور نیست یا expected_balance نیست لازم است؛ true = ثبت بعد از تأیید کاربر. اگر مانده پیامک جور باشد ابزار خودش ثبت می‌کند'),
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
                ->description('تاریخ/ساعت پیامک یا میلادی؛ خالی = امروز'),
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
        $createdCount = 0;
        $skippedCount = 0;
        $previewCount = 0;
        $failedCount = 0;
        /** @var array<int, string> $runningBalances */
        $runningBalances = [];

        [$ordered, $reordered] = $this->orderBatchChronologically($transactions);

        if ($ordered === []) {
            return __('general.ai_transaction_batch_empty');
        }

        /** @var array<int, int> $alreadyAppliedThrough Index in $ordered through which the account balance already includes SMS rows */
        $alreadyAppliedThrough = $this->findAlreadyAppliedAnchors($businessId, $ordered);

        foreach ($ordered as $offset => $entry) {
            $index = $entry['original_index'];
            $item = $entry['item'];

            if (! is_array($item)) {
                $failedCount++;
                $lines[] = __('general.ai_transaction_batch_item_failed', [
                    'n' => $index,
                    'error' => __('general.ai_transaction_invalid', [
                        'errors' => __('general.ai_error'),
                    ]),
                ]);

                continue;
            }

            $forceSkip = false;
            $accountNumber = $this->normalizeDigits((string) ($item['account_number'] ?? ''));
            $accountName = trim((string) ($item['account_name'] ?? ''));
            $account = $this->resolveAccount($businessId, $accountNumber, $accountName);

            if ($account !== null) {
                $accountId = (int) $account->id;

                if (isset($alreadyAppliedThrough[$accountId]) && $offset <= $alreadyAppliedThrough[$accountId]) {
                    $forceSkip = true;
                    $runningBalances[$accountId] = (string) ($account->current_balance ?? '0');
                }
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
            ], $confirmed, $runningBalances, $forceSkip);

            if ($result['action'] === 'created') {
                $createdCount++;
                $lines[] = __('general.ai_transaction_batch_item_ok', [
                    'n' => $index,
                    'message' => $result['message'],
                ]);
            } elseif ($result['action'] === 'skipped') {
                $skippedCount++;
                $lines[] = __('general.ai_transaction_batch_item_ok', [
                    'n' => $index,
                    'message' => $result['message'],
                ]);
            } elseif ($result['action'] === 'preview') {
                $previewCount++;
                $lines[] = __('general.ai_transaction_batch_item_ok', [
                    'n' => $index,
                    'message' => $result['message'],
                ]);
            } else {
                $failedCount++;
                $lines[] = __('general.ai_transaction_batch_item_failed', [
                    'n' => $index,
                    'error' => $result['message'],
                ]);
            }
        }

        $total = count($ordered);

        if ($previewCount === 0 && ($createdCount > 0 || $skippedCount > 0)) {
            $summary = __('general.ai_transaction_batch_summary', [
                'created' => $createdCount,
                'skipped' => $skippedCount,
                'total' => $total,
                'items' => implode("\n", $lines),
            ]);
        } else {
            $summary = __('general.ai_transaction_batch_preview_summary', [
                'ready' => $previewCount,
                'skipped' => $skippedCount,
                'total' => $total,
                'items' => implode("\n", $lines),
            ]);
        }

        if ($reordered) {
            $summary = __('general.ai_transaction_batch_reordered_by_date')."\n".$summary;
        }

        if ($previewCount > 0) {
            $summary .= "\n".__('general.ai_transaction_preview_ask_confirm');
        }

        return $summary;
    }

    /**
     * When the ledger balance already equals an SMS expected_balance, every chronological
     * row up through that SMS is treated as already registered (middle duplicates included).
     *
     * @param  list<array{original_index: int, item: mixed, sort_at: string}>  $ordered
     * @return array<int, int> accountId => last ordered offset already reflected in the ledger
     */
    protected function findAlreadyAppliedAnchors(int $businessId, array $ordered): array
    {
        $anchors = [];

        foreach ($ordered as $offset => $entry) {
            $item = $entry['item'];

            if (! is_array($item)) {
                continue;
            }

            $expectedBalance = $this->normalizeDigits((string) ($item['expected_balance'] ?? ''));

            if ($expectedBalance === '' || ! is_numeric($expectedBalance)) {
                continue;
            }

            $account = $this->resolveAccount(
                $businessId,
                $this->normalizeDigits((string) ($item['account_number'] ?? '')),
                trim((string) ($item['account_name'] ?? '')),
            );

            if ($account === null) {
                continue;
            }

            $currentBalance = (string) ($account->current_balance ?? '0');

            if (bccomp($currentBalance, $expectedBalance, 18) === 0) {
                $anchors[(int) $account->id] = $offset;
            }
        }

        return $anchors;
    }

    /**
     * @param  list<mixed>  $transactions
     * @return array{0: list<array{original_index: int, item: mixed, sort_at: string}>, 1: bool}
     */
    protected function orderBatchChronologically(array $transactions): array
    {
        $entries = [];

        foreach (array_values($transactions) as $offset => $item) {
            $originalIndex = $offset + 1;
            $sortAt = '9999-12-31 23:59:59';

            if (is_array($item)) {
                $rawDate = trim((string) ($item['transaction_date'] ?? ''));
                $resolved = $rawDate !== ''
                    ? LocaleDate::parseNearTodayDateTime($rawDate)
                    : now();

                if ($resolved !== null) {
                    $sortAt = $resolved->format('Y-m-d H:i:s');
                }
            }

            $entries[] = [
                'original_index' => $originalIndex,
                'item' => $item,
                'sort_at' => $sortAt,
            ];
        }

        $before = array_map(
            fn (array $entry): int => $entry['original_index'],
            $entries,
        );

        usort($entries, function (array $a, array $b): int {
            $dateCompare = $a['sort_at'] <=> $b['sort_at'];

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return $a['original_index'] <=> $b['original_index'];
        });

        $after = array_map(
            fn (array $entry): int => $entry['original_index'],
            $entries,
        );

        return [$entries, $before !== $after];
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
     * @return array{ok: bool, action: 'created'|'skipped'|'preview'|'failed', message: string}
     */
    protected function createOne(
        User $user,
        int $businessId,
        array $data,
        bool $confirmed,
        array &$runningBalances = [],
        bool $forceSkip = false,
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
            ? LocaleDate::toStorageDateNearToday($transactionDateInput)
            : now()->toDateString();

        if ($storageDate === null) {
            return $this->fail(__('general.ai_transaction_invalid', [
                'errors' => __('general.transaction_date'),
            ]));
        }

        $currencyCode = $account->currency?->code ?? 'IRR';
        $accountId = (int) $account->id;
        $dateWarnings = $this->dateRiskWarnings($accountId, $storageDate);
        $currentBalance = $runningBalances[$accountId]
            ?? (string) ($account->current_balance ?? '0');
        $feeAmount = null;
        $projected = $this->projectBalance($type, $currentBalance, $amount);
        $balanceMatched = false;

        if ($forceSkip || ($expectedBalance !== '' && is_numeric($expectedBalance) && bccomp($currentBalance, $expectedBalance, 18) === 0)) {
            $runningBalances[$accountId] = $currentBalance;
            $skipBalance = $expectedBalance !== '' && is_numeric($expectedBalance)
                ? $expectedBalance
                : $currentBalance;

            return $this->result(true, 'skipped', __('general.ai_transaction_already_registered', [
                'type' => $type->label(),
                'amount' => $this->formatAmount($amount),
                'account' => $account->name,
                'balance' => $this->formatAmount($skipBalance),
                'date' => LocaleDate::formatDate($storageDate),
            ]));
        }

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

            $balanceMatched = true;
        }

        $debitTotal = $feeAmount !== null
            ? bcadd($amount, $feeAmount, 18)
            : $amount;

        $shouldWrite = $confirmed || $balanceMatched;

        if (! $shouldWrite) {
            $runningBalances[$accountId] = $projected;

            $message = __('general.ai_transaction_preview_ok', [
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
            ]);

            if ($dateWarnings !== []) {
                $message .= "\n".implode("\n", $dateWarnings);
            }

            return $this->result(true, 'preview', $message);
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

                $message = __('general.ai_transaction_created_with_fee', [
                    'type' => $type->label(),
                    'amount' => $amount,
                    'fee' => $feeAmount,
                    'account' => $account->name,
                    'date' => LocaleDate::formatDate($transaction->transaction_date),
                ]);

                if ($dateWarnings !== []) {
                    $message .= "\n".implode("\n", $dateWarnings);
                }

                return $this->result(true, 'created', $message);
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

        $message = __('general.ai_transaction_created', [
            'type' => $type->label(),
            'amount' => $amount,
            'account' => $account->name,
            'date' => LocaleDate::formatDate($transaction->transaction_date),
        ]);

        if ($dateWarnings !== []) {
            $message .= "\n".implode("\n", $dateWarnings);
        }

        return $this->result(true, 'created', $message);
    }

    /**
     * @return array{ok: true, action: 'created'|'skipped'|'preview', message: string}
     */
    protected function result(bool $ok, string $action, string $message): array
    {
        return ['ok' => $ok, 'action' => $action, 'message' => $message];
    }

    /**
     * @return array{ok: false, action: 'failed', message: string}
     */
    protected function fail(string $message): array
    {
        return ['ok' => false, 'action' => 'failed', 'message' => $message];
    }

    protected function projectBalance(TransactionType $type, string $currentBalance, string $amount): string
    {
        return match ($type) {
            TransactionType::Income => bcadd($currentBalance, $amount, 18),
            TransactionType::Expense => bcsub($currentBalance, $amount, 18),
            default => $currentBalance,
        };
    }

    /**
     * @return list<string>
     */
    protected function dateRiskWarnings(int $accountId, string $storageDate): array
    {
        $warnings = [];
        $today = now()->startOfDay();
        $date = Carbon::parse($storageDate)->startOfDay();
        $daysFromToday = (int) abs($date->diffInDays($today));

        if ($daysFromToday > 2) {
            $warnings[] = __('general.ai_transaction_date_far', [
                'date' => LocaleDate::formatDate($storageDate),
                'days' => $daysFromToday,
            ]);
        }

        $lastDate = Transaction::query()
            ->where('account_id', $accountId)
            ->max('transaction_date');

        if ($lastDate !== null && $storageDate < Carbon::parse((string) $lastDate)->toDateString()) {
            $warnings[] = __('general.ai_transaction_date_out_of_order', [
                'date' => LocaleDate::formatDate($storageDate),
                'last' => LocaleDate::formatDate((string) $lastDate),
            ]);
        }

        return $warnings;
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
