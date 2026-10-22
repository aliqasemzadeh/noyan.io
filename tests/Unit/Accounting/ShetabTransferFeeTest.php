<?php

namespace Tests\Unit\Accounting;

use App\Support\Accounting\ShetabTransferFee;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShetabTransferFeeTest extends TestCase
{
    #[Test]
    #[DataProvider('irtAmounts')]
    public function it_computes_1405_fee_in_toman(string $amount, string $expectedFee): void
    {
        $this->assertSame($expectedFee, ShetabTransferFee::forAmount($amount, 'IRT'));
    }

    #[Test]
    #[DataProvider('irrAmounts')]
    public function it_computes_1405_fee_in_rial(string $amount, string $expectedFee): void
    {
        $this->assertSame($expectedFee, ShetabTransferFee::forAmount($amount, 'IRR'));
    }

    #[Test]
    public function it_returns_null_for_unsupported_currency(): void
    {
        $this->assertNull(ShetabTransferFee::forAmount('1000000', 'USD'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function irtAmounts(): array
    {
        return [
            'under_one_million' => ['500000', '1100'],
            'exactly_one_million' => ['1000000', '1100'],
            'just_over_one_million' => ['1000001', '1450'],
            'exactly_two_million' => ['2000000', '1450'],
            'exactly_three_million' => ['3000000', '1800'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function irrAmounts(): array
    {
        return [
            'exactly_one_million_toman_in_rial' => ['10000000', '11000'],
            'two_million_toman_in_rial' => ['20000000', '14500'],
        ];
    }
}
