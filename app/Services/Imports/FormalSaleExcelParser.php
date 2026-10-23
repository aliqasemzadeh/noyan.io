<?php

namespace App\Services\Imports;

use App\Enums\Accounting\FormalSaleDocumentKind;
use App\Support\LocaleDate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class FormalSaleExcelParser
{
    /**
     * @throws RuntimeException
     */
    public function parse(string $absolutePath): FormalSaleDocument
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException(__('general.ai_document_import_file_missing'));
        }

        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        $title = $this->cellString($sheet, 'B1');
        $kind = $this->detectKind($title);

        if ($kind === null) {
            throw new RuntimeException(__('general.ai_document_import_unknown_type'));
        }

        $serial = $this->cellString($sheet, 'BC1');
        $issueDateRaw = $this->cellString($sheet, 'BC3');
        $issueDate = $issueDateRaw !== ''
            ? LocaleDate::toStorageDate($issueDateRaw)
            : null;

        if ($issueDate === null && $issueDateRaw !== '') {
            throw new RuntimeException(__('general.ai_document_import_invalid_date'));
        }

        $partyName = $this->cellString($sheet, 'K14');
        $items = $this->parseItems($sheet);

        if ($items === []) {
            throw new RuntimeException(__('general.ai_document_import_no_items'));
        }

        return new FormalSaleDocument(
            kind: $kind,
            title: $title,
            serial: $serial !== '' ? $serial : null,
            issueDate: $issueDate,
            partyName: $partyName !== '' ? $partyName : null,
            items: $items,
        );
    }

    protected function detectKind(string $title): ?FormalSaleDocumentKind
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($title)) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'پیش فروش') || str_contains($normalized, 'پیش‌فروش')) {
            return FormalSaleDocumentKind::Quote;
        }

        if (str_contains($normalized, 'فروش کالا')) {
            return FormalSaleDocumentKind::Invoice;
        }

        return null;
    }

    /**
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
    protected function parseItems(Worksheet $sheet): array
    {
        $items = [];

        for ($row = 21; $row <= 50; $row++) {
            $rowLabel = $this->cellString($sheet, 'B'.$row);

            if (str_contains($rowLabel, 'جمع کل')) {
                break;
            }

            $title = $this->cellString($sheet, 'G'.$row);

            if ($title === '') {
                continue;
            }

            $quantity = $this->normalizeAmount($this->cellString($sheet, 'T'.$row));
            $unitPrice = $this->normalizeAmount($this->cellString($sheet, 'Z'.$row));

            if ($quantity === '' || ! is_numeric($quantity) || bccomp($quantity, '0', 18) !== 1) {
                continue;
            }

            if ($unitPrice === '' || ! is_numeric($unitPrice)) {
                continue;
            }

            $sku = $this->cellString($sheet, 'D'.$row);
            $unit = $this->cellString($sheet, 'W'.$row);
            $discount = $this->normalizeAmount($this->cellString($sheet, 'AL'.$row));
            $tax = $this->normalizeAmount($this->cellString($sheet, 'AX'.$row));

            $items[] = [
                'title' => $title,
                'sku' => $sku !== '' ? $sku : null,
                'unit' => $unit !== '' ? $unit : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => ($discount !== '' && is_numeric($discount)) ? $discount : '0',
                'tax_amount' => ($tax !== '' && is_numeric($tax)) ? $tax : '0',
            ];
        }

        return $items;
    }

    protected function cellString(Worksheet $sheet, string $coordinate): string
    {
        $value = $sheet->getCell($coordinate)->getCalculatedValue();

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return $this->normalizeAmount((string) $value);
        }

        return trim((string) $value);
    }

    protected function normalizeAmount(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);
        $value = str_replace(['٬', '،', ','], '', $value);

        return preg_replace('/\s+/u', '', $value) ?? '';
    }
}
