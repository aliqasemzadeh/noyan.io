<?php

namespace App\Actions\Concerns;

use App\Enums\Catalog\ProductUnit;
use App\Models\Accounting\Party;
use App\Models\Catalog\Product;
use App\Support\RichText;
use Illuminate\Validation\ValidationException;

trait NormalizesLineItems
{
    /**
     * @param  array{party_id?: int|null, party_name?: string|null}  $data
     * @return array{0: int|null, 1: string|null}
     */
    protected function resolveParty(int $businessId, array $data): array
    {
        $partyId = isset($data['party_id']) ? (int) $data['party_id'] : null;
        $partyName = filled($data['party_name'] ?? null) ? trim((string) $data['party_name']) : null;

        if ($partyId) {
            $party = Party::query()
                ->where('business_id', $businessId)
                ->whereKey($partyId)
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages([
                    'party_id' => [__('general.invoice_party_invalid')],
                ]);
            }

            return [$party->id, $partyName ?: $party->displayName()];
        }

        if ($partyName === null || $partyName === '') {
            throw ValidationException::withMessages([
                'party_name' => [__('general.invoice_party_required')],
            ]);
        }

        return [null, $partyName];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{
     *     product_id: int|null,
     *     title: string,
     *     sku: string|null,
     *     unit: string|null,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string,
     *     total: string,
     *     sort_order: int,
     *     meta: array<string, mixed>|null
     * }>
     */
    protected function normalizeItems(int $businessId, array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            $title = RichText::sanitize((string) ($item['title'] ?? ''));

            if (RichText::isBlank($title)) {
                continue;
            }

            $productId = isset($item['product_id']) && filled($item['product_id'])
                ? (int) $item['product_id']
                : null;

            $sku = filled($item['sku'] ?? null) ? trim((string) $item['sku']) : null;
            $unit = filled($item['unit'] ?? null) ? trim((string) $item['unit']) : null;

            if ($productId !== null) {
                $product = Product::query()
                    ->where('business_id', $businessId)
                    ->whereKey($productId)
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => [__('general.invoice_product_invalid')],
                    ]);
                }

                if ($sku === null && filled($product->sku)) {
                    $sku = (string) $product->sku;
                }

                if ($unit === null && $product->unit instanceof ProductUnit) {
                    $unit = $product->unit->value;
                }
            }

            if ($unit !== null && ProductUnit::tryFrom($unit) === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit" => [__('validation.in', ['attribute' => __('general.unit')])],
                ]);
            }

            $quantity = $this->normalizeAmount((string) ($item['quantity'] ?? '1'));
            $unitPrice = $this->normalizeAmount((string) ($item['unit_price'] ?? '0'));
            $discount = $this->normalizeAmount((string) ($item['discount_amount'] ?? '0'));
            $tax = $this->normalizeAmount((string) ($item['tax_amount'] ?? '0'));
            $line = bcmul($quantity, $unitPrice, 18);
            $total = bcadd(bcsub($line, $discount, 18), $tax, 18);

            $normalized[] = [
                'product_id' => $productId,
                'title' => $title,
                'sku' => $sku,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total' => $total,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'meta' => $item['meta'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{total: string, sort_order: int}>  $items
     * @return array{0: string, 1: string}
     */
    protected function calculateTotals(array $items, string $globalDiscount, string $globalTax): array
    {
        $itemsTotal = '0';

        foreach ($items as $item) {
            $itemsTotal = bcadd($itemsTotal, $item['total'], 18);
        }

        $totalAmount = bcadd(
            bcsub($itemsTotal, $this->normalizeAmount($globalDiscount), 18),
            $this->normalizeAmount($globalTax),
            18,
        );

        return [$itemsTotal, $totalAmount];
    }

    protected function normalizeAmount(string $value): string
    {
        $value = trim(str_replace(',', '', $value));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }
}
