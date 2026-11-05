<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ChecksBusinessPermission;
use App\Enums\Accounting\TransactionType;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Account;
use App\Services\Imports\BankStatementCsvDetector;
use App\Support\LocaleDate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ImportBankStatement implements Tool
{
    use ChecksBusinessPermission;

    public function description(): Stringable|string
    {
        return 'وارد کردن گردش حساب بانکی از فایل CSV یا اکسل (خروجی بانک ملت، رسالت، یا بلو بانک). ابتدا با confirmed=false پیش‌نمایش بگیر؛ فقط بعد از تأیید صریح با confirmed=true ثبت کن. file_path مسیر فایل آپلودشده است (به کاربر نشان نده). account_number را از شبا/هدر/نام فایل یا لیست حساب‌ها بفرست. اگر نام اصلی فایل را داری در original_filename بفرست.';
    }

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

        $filePath = trim((string) $request->string('file_path'));
        $accountNumber = trim((string) $request->string('account_number'));
        $originalFilename = trim((string) $request->string('original_filename'));
        $confirmed = $request->boolean('confirmed');

        if ($filePath === '') {
            return __('general.ai_bank_statement_file_missing');
        }

        $absolute = $this->resolveAbsolutePath($filePath);

        if ($absolute === null) {
            return __('general.ai_bank_statement_file_missing');
        }

        try {
            $parsed = app(BankStatementCsvDetector::class)->parse(
                $absolute,
                $originalFilename !== '' ? $originalFilename : basename($filePath),
            );
        } catch (Throwable $exception) {
            return __('general.ai_bank_statement_parse_failed', [
                'errors' => $exception->getMessage(),
            ]);
        }

        if ($accountNumber === '') {
            $accountNumber = (string) ($parsed['account_number_hint'] ?? '');
        }

        if ($accountNumber === '') {
            return __('general.ai_bank_statement_account_required');
        }

        $transactions = array_map(static function (array $row) use ($accountNumber): array {
            return [
                'type' => $row['type'],
                'amount' => $row['amount'],
                'account_number' => $accountNumber,
                'transaction_date' => $row['transaction_date'],
                'note' => $row['note'],
                'reference_number' => $row['reference_number'],
                'expected_balance' => $row['expected_balance'],
            ];
        }, $parsed['rows']);

        if (! $confirmed) {
            $previewLines = [];
            $limit = min(8, count($transactions));

            for ($i = 0; $i < $limit; $i++) {
                $row = $transactions[$i];
                $previewLines[] = __('general.ai_bank_statement_preview_line', [
                    'n' => $i + 1,
                    'type' => $row['type'],
                    'amount' => number_format((float) $row['amount']),
                    'balance' => number_format((float) $row['expected_balance']),
                    'date' => $row['transaction_date'],
                    'note' => mb_substr((string) $row['note'], 0, 60),
                ]);
            }

            if (count($transactions) > $limit) {
                $previewLines[] = __('general.ai_bank_statement_preview_more', [
                    'count' => count($transactions) - $limit,
                ]);
            }

            $preview = __('general.ai_bank_statement_preview', [
                'account' => $accountNumber,
                'total' => count($transactions),
                'items' => implode("\n", $previewLines),
            ])."\n".__('general.ai_transaction_preview_ask_confirm');

            $gate = $this->balanceGate(
                (int) $businessId,
                $accountNumber,
                $transactions,
                is_string($parsed['prior_balance'] ?? null) ? $parsed['prior_balance'] : null,
            );

            if ($gate !== null) {
                return $preview."\n".$gate;
            }

            return $preview;
        }

        $gate = $this->balanceGate(
            (int) $businessId,
            $accountNumber,
            $transactions,
            is_string($parsed['prior_balance'] ?? null) ? $parsed['prior_balance'] : null,
        );

        if ($gate !== null) {
            return $gate;
        }

        return app(CreateTransaction::class)->importParsedBatch(
            $user,
            (int) $businessId,
            $transactions,
            true,
            $accountNumber,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('مسیر فایل CSV/Excel آپلودشده (مثلاً ai-imports/xxx.csv یا ai-imports/xxx.xlsx)')
                ->required(),
            'account_number' => $schema->string()
                ->description('شماره حساب/شبا/کارت مالک صورتحساب؛ اگر خالی باشد از شبا داخل فایل یا نام فایل حدس زده می‌شود'),
            'original_filename' => $schema->string()
                ->description('نام اصلی فایل آپلودشده (مثلاً SDOC-....xlsx یا Invoice_10.3900820.1.csv) برای حدس شماره حساب'),
            'confirmed' => $schema->boolean()
                ->description('false = فقط پیش‌نمایش؛ true = ثبت بعد از تأیید کاربر'),
        ];
    }

    /**
     * @param  list<array{
     *     type: string,
     *     amount: string,
     *     account_number: string,
     *     transaction_date: string,
     *     note: string,
     *     reference_number: string,
     *     expected_balance: string
     * }>  $transactions
     */
    protected function balanceGate(
        int $businessId,
        string $accountNumber,
        array $transactions,
        ?string $priorBalance = null,
    ): ?string {
        $account = $this->resolveAccount($businessId, $accountNumber);

        if ($account === null || $transactions === []) {
            return null;
        }

        $ordered = $this->orderChronologically($transactions);
        $oldest = $ordered[0];
        $newest = $ordered[array_key_last($ordered)];

        $current = bcadd((string) ($account->current_balance ?? '0'), '0', 0);
        $newestBalance = bcadd((string) $newest['expected_balance'], '0', 0);
        $oldestBalance = bcadd((string) $oldest['expected_balance'], '0', 0);
        $oldestAmount = bcadd((string) $oldest['amount'], '0', 0);

        foreach ($ordered as $row) {
            $expected = bcadd((string) $row['expected_balance'], '0', 0);

            if (bccomp($current, $expected, 0) === 0) {
                return null;
            }
        }

        $requiredOpening = match ($oldest['type']) {
            TransactionType::Income->value, 'income' => bcsub($oldestBalance, $oldestAmount, 0),
            default => bcadd($oldestBalance, $oldestAmount, 0),
        };

        if ($priorBalance !== null && $priorBalance !== '' && is_numeric($priorBalance)) {
            $requiredOpening = bcadd($priorBalance, '0', 0);
        }

        $accountLabel = (string) $account->name;

        if (bccomp($current, $newestBalance, 0) === 1) {
            return __('general.ai_bank_statement_ledger_ahead', [
                'account' => $accountLabel,
                'current' => number_format((float) $current),
                'statement_end' => number_format((float) $newestBalance),
            ]);
        }

        if (bccomp($current, $requiredOpening, 0) !== 0) {
            return __('general.ai_bank_statement_opening_mismatch', [
                'account' => $accountLabel,
                'current' => number_format((float) $current),
                'required' => number_format((float) $requiredOpening),
                'statement_end' => number_format((float) $newestBalance),
            ]);
        }

        return null;
    }

    /**
     * @param  list<array<string, string>>  $transactions
     * @return list<array<string, string>>
     */
    protected function orderChronologically(array $transactions): array
    {
        $entries = [];

        foreach ($transactions as $offset => $item) {
            $rawDate = trim((string) ($item['transaction_date'] ?? ''));
            $resolved = $rawDate !== ''
                ? LocaleDate::parseNearTodayDateTime($rawDate)
                : null;
            $sortAt = $resolved?->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59';
            $balance = bcadd((string) ($item['expected_balance'] ?? '0'), '0', 0);

            $entries[] = [
                'item' => $item,
                'sort_at' => $sortAt,
                'balance' => $balance,
                'offset' => $offset,
            ];
        }

        usort($entries, function (array $a, array $b): int {
            $dateCompare = $a['sort_at'] <=> $b['sort_at'];

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            $balanceCompare = bccomp((string) $b['balance'], (string) $a['balance'], 0);

            if ($balanceCompare !== 0) {
                return $balanceCompare;
            }

            return $a['offset'] <=> $b['offset'];
        });

        return array_values(array_map(
            static fn (array $entry): array => $entry['item'],
            $entries,
        ));
    }

    protected function resolveAccount(int $businessId, string $accountNumber): ?Account
    {
        $identifier = preg_replace('/\s+/u', '', $accountNumber) ?? '';
        $identifier = str_replace(['-', '_'], '', $identifier);

        if ($identifier === '') {
            return null;
        }

        $query = Account::query()
            ->where('business_id', $businessId)
            ->where('is_active', true);

        $iban = strtoupper($identifier);

        if (str_starts_with($iban, 'IR') && strlen($iban) >= 26) {
            $found = (clone $query)->where('iban', $iban)->first();

            if ($found !== null) {
                return $found;
            }
        }

        $found = (clone $query)->where('account_number', $identifier)->first();

        if ($found !== null) {
            return $found;
        }

        $found = (clone $query)->where('iban', 'like', '%'.$identifier.'%')->first();

        if ($found !== null) {
            return $found;
        }

        return (clone $query)->where('account_number', 'like', '%'.$identifier.'%')->first();
    }

    protected function resolveAbsolutePath(string $filePath): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', $filePath), '/');

        if (Storage::disk('local')->exists($normalized)) {
            return Storage::disk('local')->path($normalized);
        }

        if (is_file($filePath)) {
            return $filePath;
        }

        $storagePath = storage_path('app/private/'.$normalized);

        if (is_file($storagePath)) {
            return $storagePath;
        }

        $alt = storage_path('app/'.$normalized);

        if (is_file($alt)) {
            return $alt;
        }

        return null;
    }
}
