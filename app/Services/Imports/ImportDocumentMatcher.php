<?php

namespace App\Services\Imports;

use App\Enums\Catalog\ProductUnit;
use App\Models\Accounting\Party;
use App\Models\Catalog\Product;

class ImportDocumentMatcher
{
    /**
     * @param  list<array{
     *     title: string,
     *     sku: string|null,
     *     unit: string|null,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string
     * }>  $items
     * @return list<array{
     *     product_id: int|null,
     *     title: string,
     *     sku: string|null,
     *     unit: string|null,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     product_matched: bool
     * }>
     */
    public function matchItems(int $businessId, array $items): array
    {
        return array_map(
            function (array $item) use ($businessId): array {
                $product = $this->findProduct(
                    $businessId,
                    $item['sku'] ?? null,
                    $item['title'],
                );

                $unit = $this->normalizeUnit($item['unit'] ?? null)
                    ?? ($product?->unit instanceof ProductUnit ? $product->unit->value : null);

                return [
                    'product_id' => $product?->id,
                    'title' => $item['title'],
                    'sku' => $item['sku'] ?? $product?->sku,
                    'unit' => $unit,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_amount' => $item['tax_amount'],
                    'product_matched' => $product !== null,
                ];
            },
            $items,
        );
    }

    protected function normalizeUnit(?string $unit): ?string
    {
        $unit = trim((string) $unit);

        if ($unit === '') {
            return null;
        }

        if (ProductUnit::tryFrom($unit) !== null) {
            return $unit;
        }

        $aliases = [
            'عدد' => ProductUnit::Piece->value,
            'قطعه' => ProductUnit::Piece->value,
            'کیلوگرم' => ProductUnit::Kilogram->value,
            'کیلو' => ProductUnit::Kilogram->value,
            'متر' => ProductUnit::Meter->value,
            'ساعت' => ProductUnit::Hour->value,
            'بسته' => ProductUnit::Pack->value,
            'جعبه' => ProductUnit::Box->value,
            'لیتر' => ProductUnit::Liter->value,
        ];

        return $aliases[$unit] ?? null;
    }

    public function findParty(int $businessId, FormalSaleDocument $document): ?Party
    {
        $base = Party::query()
            ->where('business_id', $businessId)
            ->where('is_active', true);

        $nationalId = trim((string) $document->partyNationalId);
        if ($nationalId !== '') {
            $byNationalId = (clone $base)->where('national_id', $nationalId)->first();

            if ($byNationalId !== null) {
                return $byNationalId;
            }
        }

        $economicCode = trim((string) $document->partyEconomicCode);
        if ($economicCode !== '') {
            $byEconomicCode = (clone $base)->where('economic_code', $economicCode)->first();

            if ($byEconomicCode !== null) {
                return $byEconomicCode;
            }
        }

        $phone = trim((string) $document->partyPhone);
        if ($phone !== '') {
            $byPhone = (clone $base)
                ->where(function ($query) use ($phone): void {
                    $query->where('phone', $phone)
                        ->orWhere('mobile', $phone);
                })
                ->first();

            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        $partyName = trim((string) $document->partyName);

        if ($partyName === '') {
            return null;
        }

        return (clone $base)
            ->where(function ($query) use ($partyName): void {
                $query->where('name', $partyName)
                    ->orWhere('legal_name', $partyName);
            })
            ->first();
    }

    public function findProduct(int $businessId, ?string $sku, string $title): ?Product
    {
        $sku = trim((string) $sku);
        $title = trim($title);

        $query = Product::query()
            ->where('business_id', $businessId)
            ->where('is_active', true);

        if ($sku !== '') {
            $bySku = (clone $query)->where('sku', $sku)->first();

            if ($bySku !== null) {
                return $bySku;
            }

            $byBarcode = (clone $query)->where('barcode', $sku)->first();

            if ($byBarcode !== null) {
                return $byBarcode;
            }
        }

        if ($title === '') {
            return null;
        }

        $byName = (clone $query)->where('name', $title)->first();

        if ($byName !== null) {
            return $byName;
        }

        $byBarcodeTitle = (clone $query)->where('barcode', $title)->first();

        if ($byBarcodeTitle !== null) {
            return $byBarcodeTitle;
        }

        return (clone $query)
            ->where('name', 'like', '%'.$title.'%')
            ->orderBy('name')
            ->first();
    }
}
