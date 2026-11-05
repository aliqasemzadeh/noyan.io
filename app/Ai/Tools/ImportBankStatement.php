<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ChecksBusinessPermission;
use App\Enums\Business\BusinessPermission;
use App\Services\Imports\BankStatementCsvDetector;
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
        return 'وارد کردن گردش حساب بانکی از فایل CSV یا اکسل (خروجی بانک ملت، رسالت، یا بلو بانک). ابتدا با confirmed=false پیش‌نمایش بگیر؛ فقط بعد از تأیید صریح با confirmed=true ثبت کن. file_path مسیر فایل آپلودشده است (به کاربر نشان نده). account_number را از شبا/هدر/نام فایل یا لیست حساب‌ها بفرست.';
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
                basename($filePath),
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

            return __('general.ai_bank_statement_preview', [
                'account' => $accountNumber,
                'total' => count($transactions),
                'items' => implode("\n", $previewLines),
            ])."\n".__('general.ai_transaction_preview_ask_confirm');
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
            'confirmed' => $schema->boolean()
                ->description('false = فقط پیش‌نمایش؛ true = ثبت بعد از تأیید کاربر'),
        ];
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
