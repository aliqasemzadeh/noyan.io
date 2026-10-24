<?php

namespace App\Ai\Tools\Concerns;

use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Services\Imports\FormalSaleDocument;
use App\Services\Imports\FormalSaleExcelParser;
use App\Services\Imports\ImportDocumentMatcher;
use App\Support\LocaleDate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait ImportsFormalSaleDocument
{
    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     document?: FormalSaleDocument,
     *     party_id?: int|null,
     *     party_name?: string|null,
     *     items?: list<array{
     *         product_id: int|null,
     *         title: string,
     *         sku: string|null,
     *         unit: string|null,
     *         quantity: string,
     *         unit_price: string,
     *         discount_amount: string,
     *         tax_amount: string,
     *         product_matched: bool
     *     }>
     * }
     */
    protected function prepareImport(
        int $businessId,
        string $filePath,
        FormalSaleDocumentKind $expectedKind,
    ): array {
        $absolutePath = $this->resolveUploadedPath($filePath);

        if ($absolutePath === null) {
            return $this->importFail(__('general.ai_document_import_file_missing'));
        }

        try {
            $document = app(FormalSaleExcelParser::class)->parse($absolutePath);
        } catch (RuntimeException $exception) {
            return $this->importFail($exception->getMessage());
        }

        if ($document->kind !== $expectedKind) {
            return $this->importFail(__('general.ai_document_import_kind_mismatch', [
                'expected' => $expectedKind->label(),
                'actual' => $document->kind->label(),
            ]));
        }

        if ($document->partyName === null || trim($document->partyName) === '') {
            return $this->importFail(__('general.ai_document_import_party_missing'));
        }

        if ($document->issueDate === null) {
            return $this->importFail(__('general.ai_document_import_invalid_date'));
        }

        $matcher = app(ImportDocumentMatcher::class);
        $party = $matcher->findParty($businessId, $document);

        if ($party === null) {
            return $this->importFail($this->partyNotFoundMessage($document));
        }

        $items = $matcher->matchItems($businessId, $document->items);

        return [
            'ok' => true,
            'message' => '',
            'document' => $document,
            'party_id' => $party->id,
            'party_name' => $document->partyName,
            'items' => $items,
        ];
    }

    protected function partyNotFoundMessage(FormalSaleDocument $document): string
    {
        $details = [];

        if (filled($document->partyNationalId)) {
            $details[] = __('general.national_id').': '.$document->partyNationalId;
        }

        if (filled($document->partyEconomicCode)) {
            $details[] = __('general.economic_code').': '.$document->partyEconomicCode;
        }

        if (filled($document->partyPhone)) {
            $details[] = __('general.phone').': '.$document->partyPhone;
        }

        if (filled($document->partyPostalCode)) {
            $details[] = __('general.postal_code').': '.$document->partyPostalCode;
        }

        if (filled($document->partyAddress)) {
            $details[] = __('general.address').': '.$document->partyAddress;
        }

        return __('general.ai_document_import_party_not_found', [
            'party' => $document->partyName,
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
        FormalSaleDocument $document,
        ?int $partyId,
        string $partyName,
        array $items,
        string $documentLabel,
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
                'qty' => $this->formatImportAmount($item['quantity']),
                'price' => $this->formatImportAmount($item['unit_price']),
                'match' => $matchLabel,
            ]);
        }

        $itemsTotal = '0';

        foreach ($items as $item) {
            $line = bcmul($item['quantity'], $item['unit_price'], 18);
            $line = bcsub($line, $item['discount_amount'], 18);
            $line = bcadd($line, $item['tax_amount'], 18);
            $itemsTotal = bcadd($itemsTotal, $line, 18);
        }

        return __('general.ai_document_import_preview', [
            'type' => $documentLabel,
            'party' => $partyName,
            'party_link' => __('general.ai_document_import_party_linked'),
            'date' => LocaleDate::formatDate($document->issueDate),
            'serial' => $document->serial ?: '—',
            'items_count' => count($items),
            'matched' => $matched,
            'total' => $this->formatImportAmount($itemsTotal),
            'items' => implode("\n", $lines),
        ]);
    }

    protected function resolveUploadedPath(string $filePath): ?string
    {
        $filePath = trim(str_replace('\\', '/', $filePath));

        if ($filePath === '') {
            return null;
        }

        $candidates = [
            $filePath,
            Storage::disk('local')->path($filePath),
            storage_path('app/private/'.$filePath),
            storage_path('app/'.$filePath),
            base_path($filePath),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{ok: false, message: string}
     */
    protected function importFail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    protected function formatImportAmount(string $amount): string
    {
        $normalized = bcadd($amount, '0', 0);

        return number_format((float) $normalized, 0, '.', ',');
    }
}
