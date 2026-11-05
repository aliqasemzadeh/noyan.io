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
use App\Support\Accounting\CategoryGlAccountMap;
use App\Support\Accounting\ShetabTransferFee;
use App\Support\LocaleDate;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
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
        return 'ثبت یک یا چند تراکنش از پیامک بانکی یا گزارش گردش حساب روی حساب موجود. برای گردش حساب، شماره/شبا هدر گزارش را در account_number ریشه بفرست (نه ذینفع ردیف و نه نام صاحب حساب)؛ روی همه ردیف‌ها اعمال می‌شود. ابزار خودش تشخیص می‌دهد شماره حساب است یا شبا یا کارت. هر ردیف را با expected_balance در transactions بفرست؛ نوع را از تغییر مانده مشخص کن (کم شدن=expense، زیاد شدن=income). اگر مانده جور باشد بدون تأیید ثبت کن. فقط وقتی مانده جور نیست پیش‌نمایش بده. تکراری‌ها skip. هرگز حساب جدید نساز.';
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
        $defaultAccountNumber = (string) $request->string('account_number');
        $defaultAccountName = (string) $request->string('account_name');

        if ($transactions !== []) {
            return $this->handleBatch(
                $user,
                (int) $businessId,
                $transactions,
                $confirmed,
                $defaultAccountNumber,
                $defaultAccountName,
            );
        }

        $result = $this->createOne($user, (int) $businessId, [
            'type' => (string) $request->string('type'),
            'amount' => (string) $request->string('amount'),
            'account_number' => $defaultAccountNumber,
            'account_name' => $defaultAccountName,
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
                ->description('شناسه حساب موجود: شماره حساب یا شبا (IR…) یا شماره کارت ۱۶ رقمی؛ ابزار خودش نوع را تشخیص می‌دهد'),
            'account_name' => $schema->string()
                ->description('نام نمایشی حساب موجود اگر شماره مشخص نیست'),
            'transaction_date' => $schema->string()
                ->description('تاریخ/ساعت پیامک مثل 05/08/02-16:18 یا 02/08 یا 1405/08/02؛ سال کوتاه را ابزار نزدیک امروز کامل می‌کند؛ خالی = امروز'),
            'note' => $schema->string()
                ->description('یادداشت اختیاری از متن پیامک'),
            'reference_number' => $schema->string()
                ->description('شماره پیگیری اختیاری'),
            'expected_balance' => $schema->string()
                ->description('مانده حساب بعد از تراکنش از پیامک یا ستون مانده ردیف گردش حساب؛ وقتی هست و جور باشد بدون تأیید ثبت می‌شود'),
        ];

        return [
            'confirmed' => $schema->boolean()
                ->description('فقط وقتی مانده جور نیست یا expected_balance نیست لازم است؛ true = ثبت بعد از تأیید کاربر. اگر مانده پیامک/ردیف جور باشد ابزار خودش ثبت می‌کند'),
            'transactions' => $schema->array()
                ->items($schema->object($itemFields))
                ->description('وقتی چند پیامک یا چند ردیف گردش حساب هست همه را اینجا بفرست (یک فراخوانی؛ در صورت نیاز chunk کن)'),
            'type' => $schema->string()
                ->description('نوع تراکنش تک‌تایی: income یا expense'),
            'amount' => $schema->string()
                ->description('مبلغ تراکنش تک‌تایی'),
            'account_number' => $schema->string()
                ->description('شناسه حساب موجود (شماره/شبا/کارت). برای گزارش گردش حساب حتماً شماره/شبا هدر گزارش را اینجا بگذار تا روی همه ردیف‌ها اعمال شود؛ هرگز شبا یا نام ذینفع/واریزکننده ردیف را اینجا نگذار'),
            'account_name' => $schema->string()
                ->description('نام نمایشی حساب از لیست حساب‌های موجود (نه نام صاحب حساب بانکی). فقط اگر شناسه عددی نیست'),
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
    protected function handleBatch(
        User $user,
        int $businessId,
        array $transactions,
        bool $confirmed,
        string $defaultAccountNumber = '',
        string $defaultAccountName = '',
    ): string {
        $lines = [];
        $createdCount = 0;
        $skippedCount = 0;
        $previewCount = 0;
        $failedCount = 0;
        /** @var array<int, string> $runningBalances */
        $runningBalances = [];
        /** @var array<string, int> $accountNotFoundCounts */
        $accountNotFoundCounts = [];
        /** @var list<int> fee txn ids waiting for the following main paya/transfer row (Mellat/Resalat: fee then amount) */
        $pendingFeeTransactionIds = [];
        /** @var int|null last non-fee txn id (Blu Bank: main then fee at same timestamp) */
        $lastMainTransactionId = null;
        /** @var array<string, mixed>|null */
        $lastMainItem = null;

        $transactions = array_map(function (mixed $item) use ($defaultAccountNumber, $defaultAccountName): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $itemAccountNumber = trim((string) ($item['account_number'] ?? ''));
            $itemAccountName = trim((string) ($item['account_name'] ?? ''));

            if ($itemAccountNumber === '' && $defaultAccountNumber !== '') {
                $item['account_number'] = $defaultAccountNumber;
            }

            if ($itemAccountName === '' && $defaultAccountName !== '') {
                $item['account_name'] = $defaultAccountName;
            }

            return $item;
        }, $transactions);

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

            $note = (string) ($item['note'] ?? '');
            $isFeeRow = $this->noteLooksLikeFee($note);

            $result = $this->createOne($user, $businessId, [
                'type' => (string) ($item['type'] ?? ''),
                'amount' => (string) ($item['amount'] ?? ''),
                'account_number' => (string) ($item['account_number'] ?? ''),
                'account_name' => (string) ($item['account_name'] ?? ''),
                'transaction_date' => (string) ($item['transaction_date'] ?? ''),
                'note' => $note,
                'reference_number' => (string) ($item['reference_number'] ?? ''),
                'expected_balance' => (string) ($item['expected_balance'] ?? ''),
                'fee_of_transaction_id' => null,
            ], $confirmed, $runningBalances, $forceSkip);

            if ($result['action'] === 'created') {
                $createdCount++;
                $createdId = $result['transaction_id'] ?? null;

                if ($createdId !== null) {
                    if ($isFeeRow) {
                        if (
                            $lastMainTransactionId !== null
                            && is_array($lastMainItem)
                            && $this->feeBelongsToMain($item, $lastMainItem)
                        ) {
                            $this->attachPendingFeesToMain([$createdId], $lastMainTransactionId);
                        } else {
                            $pendingFeeTransactionIds[] = $createdId;
                        }
                    } else {
                        $this->attachPendingFeesToMain($pendingFeeTransactionIds, $createdId);
                        $pendingFeeTransactionIds = [];
                        $lastMainTransactionId = $createdId;
                        $lastMainItem = $item;
                    }
                }

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

                if (($result['code'] ?? null) === 'account_not_found') {
                    $key = (string) ($result['lookup'] ?? '—');
                    $accountNotFoundCounts[$key] = ($accountNotFoundCounts[$key] ?? 0) + 1;
                } else {
                    $lines[] = __('general.ai_transaction_batch_item_failed', [
                        'n' => $index,
                        'error' => $result['message'],
                    ]);
                }
            }
        }

        $total = count($ordered);

        if ($accountNotFoundCounts !== [] && $createdCount === 0 && $skippedCount === 0 && $previewCount === 0) {
            $parts = [];

            foreach ($accountNotFoundCounts as $lookup => $count) {
                $parts[] = __('general.ai_transaction_batch_account_not_found', [
                    'lookup' => $lookup,
                    'count' => $count,
                    'total' => $total,
                ]);
            }

            return implode("\n", $parts);
        }

        foreach ($accountNotFoundCounts as $lookup => $count) {
            $lines[] = __('general.ai_transaction_batch_account_not_found', [
                'lookup' => $lookup,
                'count' => $count,
                'total' => $total,
            ]);
        }

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

            // Same timestamp: higher expected_balance first (earlier chronologically).
            // Covers Mellat/Resalat (fee balance higher) and Blu Bank (main balance higher).
            $aBalance = is_array($a['item']) ? $this->normalizeDigits((string) ($a['item']['expected_balance'] ?? '')) : '';
            $bBalance = is_array($b['item']) ? $this->normalizeDigits((string) ($b['item']['expected_balance'] ?? '')) : '';

            if ($aBalance !== '' && $bBalance !== '' && is_numeric($aBalance) && is_numeric($bBalance)) {
                $balanceCompare = bccomp($bBalance, $aBalance, 18);

                if ($balanceCompare !== 0) {
                    return $balanceCompare;
                }
            }

            $aFee = is_array($a['item']) ? $this->noteLooksLikeFee((string) ($a['item']['note'] ?? '')) : false;
            $bFee = is_array($b['item']) ? $this->noteLooksLikeFee((string) ($b['item']['note'] ?? '')) : false;

            if ($aFee !== $bFee) {
                return $aFee ? -1 : 1;
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
     *     expected_balance: string,
     *     fee_of_transaction_id?: int|null
     * }  $data
     * @param  array<int, string>  $runningBalances
     * @return array{ok: bool, action: 'created'|'skipped'|'preview'|'failed', message: string, code?: string|null, lookup?: string|null, transaction_id?: int|null}
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
        $feeOfTransactionId = isset($data['fee_of_transaction_id']) ? (int) $data['fee_of_transaction_id'] : null;
        if ($feeOfTransactionId !== null && $feeOfTransactionId <= 0) {
            $feeOfTransactionId = null;
        }
        $lookupLabel = trim($data['account_number']) !== ''
            ? trim($data['account_number'])
            : ($accountName !== '' ? $accountName : '—');

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
            return $this->fail(
                __('general.ai_transaction_account_not_found_with_id', [
                    'lookup' => $lookupLabel,
                ]),
                'account_not_found',
                $lookupLabel,
            );
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

        $mainCategoryId = CategoryGlAccountMap::defaultLeafCategory((int) $businessId, $type)?->id;
        $feeCategoryId = CategoryGlAccountMap::defaultLeafCategory(
            (int) $businessId,
            TransactionType::Expense,
        )?->id;

        try {
            if ($feeAmount !== null) {
                $transaction = DB::transaction(function () use ($user, $type, $amount, $feeAmount, $note, $payloadBase, $mainCategoryId, $feeCategoryId) {
                    $main = app(ProcessTransactionAction::class)->handle($user, [
                        ...$payloadBase,
                        'type' => $type,
                        'amount' => $amount,
                        'category_id' => $mainCategoryId,
                        'note' => $note !== '' ? $note : null,
                    ]);

                    app(ProcessTransactionAction::class)->handle($user, [
                        ...$payloadBase,
                        'type' => TransactionType::Expense,
                        'amount' => $feeAmount,
                        'category_id' => $feeCategoryId,
                        'note' => __('general.ai_transaction_fee_note'),
                        'meta' => ['fee_of_transaction_id' => $main->id],
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

                return $this->result(true, 'created', $message, transactionId: (int) $transaction->id);
            }

            $meta = null;

            if ($feeOfTransactionId !== null) {
                $meta = ['fee_of_transaction_id' => $feeOfTransactionId];
            }

            $transaction = app(ProcessTransactionAction::class)->handle($user, [
                ...$payloadBase,
                'type' => $type,
                'amount' => $amount,
                'category_id' => $this->noteLooksLikeFee($note) ? $feeCategoryId : $mainCategoryId,
                'note' => $note !== '' ? $note : null,
                'meta' => $meta,
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

        return $this->result(true, 'created', $message, transactionId: (int) $transaction->id);
    }

    /**
     * Import a pre-parsed bank statement batch (used by CSV import tool).
     *
     * @param  list<array<string, mixed>>  $transactions
     */
    public function importParsedBatch(
        User $user,
        int $businessId,
        array $transactions,
        bool $confirmed,
        string $defaultAccountNumber = '',
        string $defaultAccountName = '',
    ): string {
        return $this->handleBatch(
            $user,
            $businessId,
            $transactions,
            $confirmed,
            $defaultAccountNumber,
            $defaultAccountName,
        );
    }

    protected function noteLooksLikeFee(string $note): bool
    {
        $haystack = mb_strtolower($note);

        return str_contains($haystack, 'کارمزد') || str_contains($haystack, 'كارمزد');
    }

    /**
     * @param  list<int>  $feeIds
     */
    protected function attachPendingFeesToMain(array $feeIds, int $mainTransactionId): void
    {
        if ($feeIds === []) {
            return;
        }

        foreach ($feeIds as $feeId) {
            $fee = Transaction::query()->whereKey($feeId)->first();

            if ($fee === null) {
                continue;
            }

            $meta = is_array($fee->meta) ? $fee->meta : [];
            $meta['fee_of_transaction_id'] = $mainTransactionId;
            $fee->forceFill(['meta' => $meta])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $feeItem
     * @param  array<string, mixed>  $mainItem
     */
    protected function feeBelongsToMain(array $feeItem, array $mainItem): bool
    {
        $feeRef = $this->normalizeDigits((string) ($feeItem['reference_number'] ?? ''));
        $mainRef = $this->normalizeDigits((string) ($mainItem['reference_number'] ?? ''));

        if ($feeRef !== '' && $mainRef !== '' && $feeRef === $mainRef) {
            return true;
        }

        $feeDate = trim((string) ($feeItem['transaction_date'] ?? ''));
        $mainDate = trim((string) ($mainItem['transaction_date'] ?? ''));

        return $feeDate !== '' && $mainDate !== '' && $feeDate === $mainDate;
    }

    /**
     * @return array{ok: true, action: 'created'|'skipped'|'preview', message: string, code?: null, lookup?: null, transaction_id?: int|null}
     */
    protected function result(bool $ok, string $action, string $message, ?int $transactionId = null): array
    {
        $payload = ['ok' => $ok, 'action' => $action, 'message' => $message];

        if ($transactionId !== null) {
            $payload['transaction_id'] = $transactionId;
        }

        return $payload;
    }

    /**
     * @return array{ok: false, action: 'failed', message: string, code: string|null, lookup: string|null}
     */
    protected function fail(string $message, ?string $code = null, ?string $lookup = null): array
    {
        return [
            'ok' => false,
            'action' => 'failed',
            'message' => $message,
            'code' => $code,
            'lookup' => $lookup,
        ];
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

        $identifier = $this->normalizeAccountIdentifier($accountNumber);

        if ($identifier !== '') {
            $account = $this->findAccountByIdentifier($query, $identifier);

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

    /**
     * Match account_number, IBAN (IR…), or 16-digit card — type is inferred from the value.
     *
     * @param  Builder<Account>  $query
     */
    protected function findAccountByIdentifier($query, string $identifier): ?Account
    {
        $iban = $this->normalizeIban($identifier);
        $digits = $this->digitsOnly($identifier);
        $accounts = (clone $query)->get();

        if ($iban !== '') {
            $account = $accounts->first(
                fn (Account $account): bool => $this->normalizeIban((string) ($account->iban ?? '')) === $iban
            );

            if ($account !== null) {
                return $account;
            }
        }

        if (strlen($digits) === 16) {
            $account = $accounts->first(
                fn (Account $account): bool => $this->digitsOnly((string) ($account->card_number ?? '')) === $digits
            );

            if ($account !== null) {
                return $account;
            }
        }

        if ($digits !== '') {
            $account = $accounts->first(
                fn (Account $account): bool => $this->digitsOnly((string) ($account->account_number ?? '')) === $digits
                    || $this->normalizeAccountIdentifier((string) ($account->account_number ?? '')) === $identifier
            );

            if ($account !== null) {
                return $account;
            }
        }

        return $accounts->first(function (Account $account) use ($identifier, $iban, $digits): bool {
            $candidates = [
                $this->normalizeAccountIdentifier((string) ($account->account_number ?? '')),
                $this->digitsOnly((string) ($account->account_number ?? '')),
                $this->normalizeIban((string) ($account->iban ?? '')),
                $this->digitsOnly((string) ($account->card_number ?? '')),
            ];

            return in_array($identifier, $candidates, true)
                || ($iban !== '' && in_array($iban, $candidates, true))
                || ($digits !== '' && in_array($digits, $candidates, true));
        });
    }

    protected function normalizeAccountIdentifier(string $value): string
    {
        $value = $this->normalizeDigits($value);
        $value = strtoupper($value);

        return preg_replace('/[^A-Z0-9]/u', '', $value) ?? '';
    }

    protected function normalizeIban(string $value): string
    {
        $normalized = $this->normalizeAccountIdentifier($value);

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'IR') && strlen($normalized) === 26) {
            return $normalized;
        }

        if (! str_starts_with($normalized, 'IR') && strlen($normalized) === 24 && ctype_digit($normalized)) {
            return 'IR'.$normalized;
        }

        if (str_starts_with($normalized, 'IR')) {
            return $normalized;
        }

        return '';
    }

    protected function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $this->normalizeDigits($value)) ?? '';
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
