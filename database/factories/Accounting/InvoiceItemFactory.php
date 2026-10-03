<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = '1';
        $unitPrice = '100000';
        $discount = '0';
        $tax = '0';
        $line = bcmul($quantity, $unitPrice, 18);
        $total = bcadd(bcsub($line, $discount, 18), $tax, 18);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'title' => fake()->words(3, true),
            'sort_order' => 0,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $total,
            'meta' => null,
        ];
    }
}
