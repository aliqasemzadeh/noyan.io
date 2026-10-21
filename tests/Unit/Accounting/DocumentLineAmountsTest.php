<?php

namespace Tests\Unit\Accounting;

use App\Support\Accounting\DocumentLineAmounts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentLineAmountsTest extends TestCase
{
    #[Test]
    public function it_computes_line_and_column_totals(): void
    {
        $item = (object) [
            'quantity' => '2',
            'unit_price' => '150000',
            'discount_amount' => '10000',
            'tax_amount' => '5000',
        ];

        $line = DocumentLineAmounts::forItem($item);

        $this->assertSame('300000.000000000000000000', $line['line_total']);
        $this->assertSame('290000.000000000000000000', $line['after_discount']);
        $this->assertSame('295000.000000000000000000', $line['final_total']);

        $totals = DocumentLineAmounts::columnTotals([$item, $item]);

        $this->assertSame('600000.000000000000000000', $totals['line_total']);
        $this->assertSame('590000.000000000000000000', $totals['final_total']);
    }
}
