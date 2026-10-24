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
        $partyEconomicCode = $this->valueAfterLabel($sheet, 'AC14', ['شماره اقتصادی']);
        $partyNationalId = $this->valueAfterLabel($sheet, 'AU16', ['شناسه ملی']);
        $partyPostalCode = $this->valueAfterLabel($sheet, 'AB16', ['کد پستی ده رقمی', 'کد پستی']);
        $partyPhone = $this->valueAfterLabel($sheet, 'AY18', ['تلفن / فکس', 'تلفن', 'فکس']);
        $partyAddress = $this->resolveBuyerAddress($sheet);

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
            partyEconomicCode: $partyEconomicCode,
            partyNationalId: $partyNationalId,
            partyPostalCode: $partyPostalCode,
            partyPhone: $partyPhone,
            partyAddress: $partyAddress,
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

    protected function resolveBuyerAddress(Worksheet $sheet): ?string
    {
        $address = $this->valueAfterLabel($sheet, 'E18', ['نشانی'])
            ?? $this->nonEmptyCell($sheet, 'E18');
        $province = $this->nonEmptyCell($sheet, 'I16');
        $city = $this->nonEmptyCell($sheet, 'U16');

        $parts = array_values(array_filter(
            [$address, $province !== null ? 'استان '.$province : null, $city !== null ? 'شهرستان '.$city : null],
            fn (?string $part): bool => $part !== null && $part !== '',
        ));

        if ($parts === []) {
            return null;
        }

        return implode('، ', $parts);
    }

    protected function nonEmptyCell(Worksheet $sheet, string $coordinate): ?string
    {
        $value = $this->cellString($sheet, $coordinate);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $labels
     */
    protected function valueAfterLabel(Worksheet $sheet, string $coordinate, array $labels): ?string
    {
        $raw = $this->cellString($sheet, $coordinate);

        if ($raw === '') {
            return null;
        }

        foreach ($labels as $label) {
            $pattern = '/^'.preg_quote($label, '/').'\s*:?\s*/u';
            $stripped = preg_replace($pattern, '', $raw);

            if (is_string($stripped) && $stripped !== $raw) {
                $raw = trim($stripped);
                break;
            }
        }

        $raw = trim($raw);
        $raw = rtrim($raw, ':');
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        foreach ($labels as $label) {
            if ($raw === $label || $raw === $label.':') {
                return null;
            }
        }

        return $this->normalizeDigits($raw);
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
        $value = $this->normalizeDigits($value);
        $value = str_replace(['٬', '،', ','], '', $value);

        return preg_replace('/\s+/u', '', $value) ?? '';
    }

    protected function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);

        return str_replace($arabic, $english, $value);
    }
}
