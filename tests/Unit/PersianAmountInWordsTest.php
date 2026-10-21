<?php

namespace Tests\Unit;

use App\Support\PersianAmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersianAmountInWordsTest extends TestCase
{
    #[Test]
    #[DataProvider('amounts')]
    public function it_converts_amounts_to_persian_words(string $amount, string $expected): void
    {
        $this->assertSame($expected, PersianAmountInWords::convert($amount));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function amounts(): array
    {
        return [
            'zero' => ['0', 'صفر ریال'],
            'simple' => ['125', 'صد و بیست و پنج ریال'],
            'thousands' => ['1500', 'یک هزار و پانصد ریال'],
            'millions' => ['1000000', 'یک میلیون ریال'],
            'formatted' => ['1,250,000', 'یک میلیون و دویست و پنجاه هزار ریال'],
            'without_unit' => ['10', 'ده ریال'],
        ];
    }

    #[Test]
    public function it_can_omit_rial_suffix(): void
    {
        $this->assertSame('ده', PersianAmountInWords::convert('10', withRial: false));
    }
}
