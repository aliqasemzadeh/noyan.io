<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ChecksBusinessPermission;
use App\Ai\Tools\Concerns\ImportsFormalSaleDocument;
use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Enums\Business\BusinessPermission;
use App\Jobs\Accounting\ImportFormalSaleDocumentJob;
use App\Services\Imports\FormalSaleDocument;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ImportInvoice implements Tool
{
    use ChecksBusinessPermission;
    use ImportsFormalSaleDocument;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'وارد کردن فاکتور فروش از فایل اکسل رسمی (عنوان «فروش کالا و خدمات»). متنِ فاکتور به‌تنهایی با این ابزار قابل ثبت نیست؛ برای متن از create_invoice استفاده کن. ابتدا با confirmed=false پیش‌نمایش بگیر؛ فقط بعد از تأیید صریح کاربر با confirmed=true ثبت را در صف بگذار. file_path مسیر فایل آپلودشده است (داده داخلی؛ به کاربر نشان نده).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($denied = $this->denyUnlessCan(BusinessPermission::InvoiceCreate)) {
            return $denied;
        }

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

        $prepared = $this->prepareImport((int) $businessId, $filePath, FormalSaleDocumentKind::Invoice);

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
                __('general.invoice'),
            )."\n".__('general.ai_transaction_preview_ask_confirm');
        }

        ImportFormalSaleDocumentJob::dispatch(
            $user->id,
            (int) $businessId,
            $filePath,
            FormalSaleDocumentKind::Invoice->value,
        );

        return __('general.ai_document_import_queued');
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
                ->description('false یا خالی = فقط پیش‌نمایش بدون ثبت؛ true = قرار دادن ثبت پیش‌نویس فاکتور در صف فقط بعد از تأیید کاربر'),
        ];
    }
}
