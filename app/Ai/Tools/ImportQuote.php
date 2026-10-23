<?php

namespace App\Ai\Tools;

use App\Actions\Quotes\SaveQuoteAction;
use App\Ai\Tools\Concerns\ImportsFormalSaleDocument;
use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Services\Imports\FormalSaleDocument;
use App\Support\LocaleDate;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ImportQuote implements Tool
{
    use ImportsFormalSaleDocument;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'وارد کردن پیش‌فاکتور / استعلام قیمت / پیشنهاد قیمت از فایل اکسل رسمی (عنوان «پیش فروش کالا و خدمات»). ابتدا با confirmed=false پیش‌نمایش بگیر؛ فقط بعد از تأیید صریح کاربر با confirmed=true ثبت کن. file_path مسیر فایل آپلودشده است. اگر کالا پیدا شد وصل می‌شود؛ اگر نه بدون اتصال ثبت می‌شود.';
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

        $filePath = trim((string) $request->string('file_path'));
        $confirmed = $request->boolean('confirmed');

        if ($filePath === '') {
            return __('general.ai_document_import_file_missing');
        }

        $prepared = $this->prepareImport((int) $businessId, $filePath, FormalSaleDocumentKind::Quote);

        if (! $prepared['ok']) {
            return $prepared['message'];
        }

        /** @var FormalSaleDocument $document */
        $document = $prepared['document'];
        $items = $prepared['items'];
        $partyId = $prepared['party_id'];
        $partyName = (string) $prepared['party_name'];

        if (! $confirmed) {
            return $this->buildPreviewMessage(
                $document,
                $partyId,
                $partyName,
                $items,
                __('general.quote'),
            )."\n".__('general.ai_transaction_preview_ask_confirm');
        }

        $issueDate = (string) $document->issueDate;
        $validUntil = Carbon::parse($issueDate)->addDays(7)->toDateString();

        try {
            $quote = app(SaveQuoteAction::class)->handle($user, [
                'party_id' => $partyId,
                'party_name' => $partyName,
                'issue_date' => $issueDate,
                'valid_until' => $validUntil,
                'note' => $document->serial !== null
                    ? __('general.ai_document_import_note_serial', ['serial' => $document->serial])
                    : null,
                'meta' => [
                    'imported_from' => 'excel',
                    'source_serial' => $document->serial,
                    'source_title' => $document->title,
                    'source_file' => $filePath,
                ],
                'items' => array_map(fn (array $item): array => [
                    'product_id' => $item['product_id'],
                    'title' => $item['title'],
                    'sku' => $item['sku'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_amount' => $item['tax_amount'],
                ], $items),
            ]);
        } catch (ValidationException $exception) {
            return __('general.ai_document_import_invalid', [
                'errors' => $exception->validator->errors()->first() ?: __('general.ai_error'),
            ]);
        }

        $matched = count(array_filter($items, fn (array $item): bool => $item['product_matched']));

        return __('general.ai_quote_import_created', [
            'number' => $quote->quote_number,
            'party' => $partyName,
            'items' => count($items),
            'matched' => $matched,
            'total' => $this->formatImportAmount((string) $quote->total_amount),
            'date' => LocaleDate::formatDate($quote->issue_date),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('مسیر فایل اکسل آپلودشده (مثلاً ai-imports/xxx.xlsx یا مسیر نسبی در پروژه)')
                ->required(),
            'confirmed' => $schema->boolean()
                ->description('false یا خالی = فقط پیش‌نمایش بدون ثبت؛ true = ثبت پیش‌نویس پیشنهاد قیمت فقط بعد از تأیید کاربر'),
        ];
    }
}
