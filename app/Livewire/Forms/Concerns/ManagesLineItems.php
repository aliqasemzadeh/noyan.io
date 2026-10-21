<?php

namespace App\Livewire\Forms\Concerns;

use App\Support\Money;
use App\Support\RichText;
use Illuminate\Support\Str;

trait ManagesLineItems
{
    public function addItem(): void
    {
        $this->items[] = [
            'id' => (string) Str::uuid(),
            'product_id' => null,
            'title' => '',
            'sku' => '',
            'unit' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'discount_amount' => '0',
            'tax_amount' => '0',
            'total' => '0',
            'sort_order' => count($this->items),
        ];
    }

    public function removeItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->reindexSortOrder();
        $this->recalculate();
    }

    public function updateSortOrder(string|int $id, int $position): void
    {
        $id = (string) $id;
        $items = collect($this->items)->values();
        $currentIndex = $items->search(fn (array $item): bool => (string) $item['id'] === $id);

        if ($currentIndex === false) {
            return;
        }

        $item = $items->pull($currentIndex);
        $items = $items->values();
        $items->splice($position, 0, [$item]);

        $this->items = $items->values()->all();
        $this->reindexSortOrder();
    }

    public function useFreeTextItem(int $index, string $title): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['product_id'] = null;
        $this->items[$index]['title'] = RichText::fromPlain($title);
        $this->recalculate();
    }

    public function recalculate(): void
    {
        foreach ($this->items as $index => $item) {
            $quantity = $this->normalizeAmount((string) ($item['quantity'] ?? '0'));
            $unitPrice = $this->normalizeAmount((string) ($item['unit_price'] ?? '0'));
            $discount = $this->normalizeAmount((string) ($item['discount_amount'] ?? '0'));
            $tax = $this->normalizeAmount((string) ($item['tax_amount'] ?? '0'));
            $line = bcmul($quantity, $unitPrice, 18);
            $total = bcadd(bcsub($line, $discount, 18), $tax, 18);

            $this->items[$index]['quantity'] = $this->formatAmount($quantity);
            $this->items[$index]['unit_price'] = $this->formatAmount($unitPrice);
            $this->items[$index]['discount_amount'] = $this->formatAmount($discount);
            $this->items[$index]['tax_amount'] = $this->formatAmount($tax);
            $this->items[$index]['total'] = $this->formatAmount($total);
            $this->items[$index]['sort_order'] = $index;
        }
    }

    public function itemsTotal(): string
    {
        $this->recalculate();
        $total = '0';

        foreach ($this->items as $item) {
            $total = bcadd($total, $this->normalizeAmount((string) ($item['total'] ?? '0')), 18);
        }

        return $this->formatAmount($total);
    }

    public function grandTotal(): string
    {
        $itemsTotal = $this->normalizeAmount($this->itemsTotal());
        $discount = $this->normalizeAmount($this->global_discount);
        $tax = $this->normalizeAmount($this->global_tax);

        return $this->formatAmount(bcadd(bcsub($itemsTotal, $discount, 18), $tax, 18));
    }

    protected function reindexSortOrder(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['sort_order'] = $index;
        }
    }

    protected function normalizeAmount(string $value): string
    {
        $value = Money::normalize($value);

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }

    protected function formatAmount(string $value): string
    {
        $normalized = $this->normalizeAmount($value);
        $trimmed = rtrim(rtrim($normalized, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
