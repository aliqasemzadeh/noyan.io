<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

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
            'quote_id' => Quote::factory(),
            'product_id' => null,
            'title' => RichText::fromPlain(fake()->words(3, true)),
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
