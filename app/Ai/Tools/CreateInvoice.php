<?php

namespace App\Ai\Tools;

use App\Actions\Invoices\SaveInvoiceAction;
use App\Ai\Tools\Concerns\ChecksBusinessPermission;
use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Models\Accounting\Party;
use App\Services\Imports\FormalSaleDocument;
use App\Services\Imports\ImportDocumentMatcher;
use App\Support\LocaleDate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateInvoice implements Tool
{
    use ChecksBusinessPermission;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'ثبت فاکتور فروش یا خرید از متن کاربر (بدون فایل اکسل). اقلام، طرف‌حساب، نوع (sale/purchase)، تاریخ و مبالغ را استخراج کن. ابتدا با confirmed=false پیش‌نمایش بگیر؛ فقط بعد از تأیید صریح کاربر با confirmed=true پیش‌نویس را ثبت کن. برای فایل اکسل رسمی از import_invoice استفاده کن.';
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

        $confirmed = $request->boolean('confirmed');
        $typeValue = strtolower(trim((string) $request->string('type')));
        $type = InvoiceType::tryFrom($typeValue);

        if ($type === null || ! in_array($type, InvoiceType::creatable(), true)) {
            return __('general.ai_invoice_invalid_type');
        }

        $partyName = trim((string) $request->string('party_name'));
        $partyNationalId = $this->normalizeDigits((string) $request->string('party_national_id'));
        $partyEconomicCode = $this->normalizeDigits((string) $request->string('party_economic_code'));
        $partyPhone = $this->normalizeDigits((string) $request->string('party_phone'));
        $issueDateInput = trim((string) $request->string('issue_date'));
        $dueDateInput = trim((string) $request->string('due_date'));
        $note = trim((string) $request->string('note'));
        $globalDiscount = $this->normalizeDigits((string) $request->string('global_discount'));
        $globalTax = $this->normalizeDigits((string) $request->string('global_tax'));
        $rawItems = $request->array('items');

        if ($partyName === '') {
            return __('general.ai_invoice_party_missing');
        }

        $issueDate = $issueDateInput !== ''
            ? LocaleDate::toStorageDateNearToday($issueDateInput)
            : now()->toDateString();

        if ($issueDate === null) {
            return __('general.ai_invoice_invalid_date');
        }

        $dueDate = null;

        if ($dueDateInput !== '') {
            $dueDate = LocaleDate::toStorageDateNearToday($dueDateInput);

            if ($dueDate === null) {
                return __('general.ai_invoice_invalid_date');
            }
        }

        $normalizedItems = $this->normalizeIncomingItems($rawItems);

        if ($normalizedItems === []) {
            return __('general.ai_invoice_no_items');
        }

        $matcher = app(ImportDocumentMatcher::class);
        $party = $this->findParty(
            $matcher,
            (int) $businessId,
            $partyName,
            $partyNationalId,
            $partyEconomicCode,
            $partyPhone,
        );

        if ($party === null) {
            return $this->partyNotFoundMessage(
                $partyName,
                $partyNationalId,
                $partyEconomicCode,
                $partyPhone,
            );
        }

        $items = $matcher->matchItems((int) $businessId, $normalizedItems);
        $total = $this->calculateItemsTotal($items, $globalDiscount, $globalTax);

        if (! $confirmed) {
            return $this->buildPreviewMessage(
                $type,
                $party,
                $issueDate,
                $items,
                $total,
            )."\n".__('general.ai_invoice_preview_ask_confirm');
        }

        $payloadItems = array_map(fn (array $item): array => [
            'product_id' => $item['product_id'],
            'title' => $item['title'],
            'sku' => $item['sku'],
            'unit' => $item['unit'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_amount' => $item['discount_amount'],
            'tax_amount' => $item['tax_amount'],
        ], $items);

        try {
            $invoice = app(SaveInvoiceAction::class)->handle($user, [
                'type' => $type,
                'party_id' => $party->id,
                'party_name' => $party->displayName(),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'global_discount' => $globalDiscount !== '' ? $globalDiscount : '0',
                'global_tax' => $globalTax !== '' ? $globalTax : '0',
                'note' => $note !== '' ? $note : null,
                'meta' => [
                    'imported_from' => 'ai_text',
                ],
                'items' => $payloadItems,
            ]);
        } catch (ValidationException $exception) {
            return __('general.ai_invoice_invalid', [
                'errors' => $exception->validator->errors()->first() ?: __('general.ai_error'),
            ]);
        }

        $matched = count(array_filter($items, fn (array $item): bool => $item['product_matched']));

        return __('general.ai_invoice_created', [
            'type' => $type->label(),
            'number' => $invoice->invoice_number,
            'party' => $party->displayName(),
            'items' => count($items),
            'matched' => $matched,
            'total' => $this->formatAmount((string) $invoice->total_amount),
            'date' => LocaleDate::formatDate($invoice->issue_date),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        $itemFields = fn (JsonSchema $schema): array => [
            'title' => $schema->string()
                ->description('نام کالا/خدمت؛ عین متن کاربر')
                ->required(),
            'quantity' => $schema->string()
                ->description('تعداد؛ ارقام فارسی و کاما مجاز است')
                ->required(),
            'unit_price' => $schema->string()
                ->description('قیمت واحد؛ ارقام فارسی و کاما مجاز است')
                ->required(),
            'discount_amount' => $schema->string()
                ->description('تخفیف این قلم؛ اختیاری'),
            'tax_amount' => $schema->string()
                ->description('مالیات این قلم؛ اختیاری'),
            'sku' => $schema->string()
                ->description('کد کالا در صورت وجود'),
            'unit' => $schema->string()
                ->description('واحد مثل عدد، کیلوگرم، بسته'),
        ];

        return [
            'type' => $schema->string()
                ->description('sale برای فاکتور فروش، purchase برای فاکتور خرید')
                ->required(),
            'party_name' => $schema->string()
                ->description('نام طرف‌حساب (مشتری برای فروش، تأمین‌کننده برای خرید)؛ عین متن کاربر')
                ->required(),
            'party_national_id' => $schema->string()
                ->description('کد/شناسه ملی طرف‌حساب در صورت وجود'),
            'party_economic_code' => $schema->string()
                ->description('کد اقتصادی طرف‌حساب در صورت وجود'),
            'party_phone' => $schema->string()
                ->description('تلفن یا موبایل طرف‌حساب در صورت وجود'),
            'issue_date' => $schema->string()
                ->description('تاریخ صدور مثل 1405/07/27 یا میلادی؛ خالی = امروز'),
            'due_date' => $schema->string()
                ->description('تاریخ سررسید اختیاری'),
            'note' => $schema->string()
                ->description('یادداشت اختیاری'),
            'global_discount' => $schema->string()
                ->description('تخفیف کلی فاکتور؛ اختیاری'),
            'global_tax' => $schema->string()
                ->description('مالیات کلی فاکتور؛ اختیاری'),
            'items' => $schema->array()
                ->items($schema->object($itemFields))
                ->description('اقلام فاکتور؛ حداقل یک قلم با عنوان، تعداد و قیمت واحد')
                ->required(),
            'confirmed' => $schema->boolean()
                ->description('false یا خالی = فقط پیش‌نمایش بدون ثبت؛ true = ثبت پیش‌نویس فقط بعد از تأیید کاربر'),
        ];
    }

    /**
     * @param  list<mixed>  $rawItems
     * @return list<array{
     *     title: string,
     *     sku: string|null,
     *     unit: string|null,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string
     * }>
     */
    protected function normalizeIncomingItems(array $rawItems): array
    {
        $items = [];

        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $title = trim((string) ($raw['title'] ?? ''));
            $quantity = $this->normalizeDigits((string) ($raw['quantity'] ?? ''));
            $unitPrice = $this->normalizeDigits((string) ($raw['unit_price'] ?? ''));
            $discount = $this->normalizeDigits((string) ($raw['discount_amount'] ?? ''));
            $tax = $this->normalizeDigits((string) ($raw['tax_amount'] ?? ''));
            $sku = trim((string) ($raw['sku'] ?? ''));
            $unit = trim((string) ($raw['unit'] ?? ''));

            if ($title === '') {
                continue;
            }

            if ($quantity === '' || ! is_numeric($quantity) || bccomp($quantity, '0', 18) !== 1) {
                continue;
            }

            if ($unitPrice === '' || ! is_numeric($unitPrice) || bccomp($unitPrice, '0', 18) === -1) {
                continue;
            }

            $items[] = [
                'title' => $title,
                'sku' => $sku !== '' ? $sku : null,
                'unit' => $unit !== '' ? $unit : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount !== '' && is_numeric($discount) ? $discount : '0',
                'tax_amount' => $tax !== '' && is_numeric($tax) ? $tax : '0',
            ];
        }

        return $items;
    }

    protected function findParty(
        ImportDocumentMatcher $matcher,
        int $businessId,
        string $partyName,
        string $nationalId,
        string $economicCode,
        string $phone,
    ): ?Party {
        $document = new FormalSaleDocument(
            kind: FormalSaleDocumentKind::Invoice,
            title: '',
            serial: null,
            issueDate: null,
            partyName: $partyName,
            partyEconomicCode: $economicCode !== '' ? $economicCode : null,
            partyNationalId: $nationalId !== '' ? $nationalId : null,
            partyPostalCode: null,
            partyPhone: $phone !== '' ? $phone : null,
            partyAddress: null,
            items: [],
        );

        return $matcher->findParty($businessId, $document);
    }

    protected function partyNotFoundMessage(
        string $partyName,
        string $nationalId,
        string $economicCode,
        string $phone,
    ): string {
        $details = [];

        if ($nationalId !== '') {
            $details[] = __('general.national_id').': '.$nationalId;
        }

        if ($economicCode !== '') {
            $details[] = __('general.economic_code').': '.$economicCode;
        }

        if ($phone !== '') {
            $details[] = __('general.phone').': '.$phone;
        }

        return __('general.ai_invoice_party_not_found', [
            'party' => $partyName,
            'details' => $details === []
                ? __('general.ai_document_import_party_not_found_no_details')
                : implode(' | ', $details),
        ]);
    }

    /**
     * @param  list<array{
     *     product_id: int|null,
     *     title: string,
     *     sku: string|null,
     *     unit: string|null,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     product_matched: bool
     * }>  $items
     */
    protected function buildPreviewMessage(
        InvoiceType $type,
        Party $party,
        string $issueDate,
        array $items,
        string $total,
    ): string {
        $matched = count(array_filter($items, fn (array $item): bool => $item['product_matched']));
        $lines = [];

        foreach ($items as $index => $item) {
            $matchLabel = $item['product_matched']
                ? __('general.ai_document_import_product_linked')
                : __('general.ai_document_import_product_unlinked');

            $lines[] = __('general.ai_document_import_item_line', [
                'n' => $index + 1,
                'title' => $item['title'],
                'qty' => $this->formatAmount($item['quantity']),
                'price' => $this->formatAmount($item['unit_price']),
                'match' => $matchLabel,
            ]);
        }

        return __('general.ai_invoice_create_preview', [
            'type' => $type->label(),
            'party' => $party->displayName(),
            'party_link' => __('general.ai_document_import_party_linked'),
            'date' => LocaleDate::formatDate($issueDate),
            'items_count' => count($items),
            'matched' => $matched,
            'total' => $this->formatAmount($total),
            'items' => implode("\n", $lines),
        ]);
    }

    /**
     * @param  list<array{
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string
     * }>  $items
     */
    protected function calculateItemsTotal(array $items, string $globalDiscount, string $globalTax): string
    {
        $itemsTotal = '0';

        foreach ($items as $item) {
            $line = bcmul($item['quantity'], $item['unit_price'], 18);
            $line = bcsub($line, $item['discount_amount'], 18);
            $line = bcadd($line, $item['tax_amount'], 18);
            $itemsTotal = bcadd($itemsTotal, $line, 18);
        }

        $discount = $globalDiscount !== '' && is_numeric($globalDiscount) ? $globalDiscount : '0';
        $tax = $globalTax !== '' && is_numeric($globalTax) ? $globalTax : '0';

        return bcadd(bcsub($itemsTotal, $discount, 18), $tax, 18);
    }

    protected function formatAmount(string $amount): string
    {
        $normalized = bcadd($amount, '0', 0);

        return number_format((float) $normalized, 0, '.', ',');
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
