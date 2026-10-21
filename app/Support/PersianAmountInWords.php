<?php

namespace App\Support;

final class PersianAmountInWords
{
    /**
     * @var list<string>
     */
    private const ONES = [
        '',
        'یک',
        'دو',
        'سه',
        'چهار',
        'پنج',
        'شش',
        'هفت',
        'هشت',
        'نه',
    ];

    /**
     * @var list<string>
     */
    private const TEENS = [
        'ده',
        'یازده',
        'دوازده',
        'سیزده',
        'چهارده',
        'پانزده',
        'شانزده',
        'هفده',
        'هجده',
        'نوزده',
    ];

    /**
     * @var list<string>
     */
    private const TENS = [
        '',
        '',
        'بیست',
        'سی',
        'چهل',
        'پنجاه',
        'شصت',
        'هفتاد',
        'هشتاد',
        'نود',
    ];

    /**
     * @var list<string>
     */
    private const HUNDREDS = [
        '',
        'صد',
        'دویست',
        'سیصد',
        'چهارصد',
        'پانصد',
        'ششصد',
        'هفتصد',
        'هشتصد',
        'نهصد',
    ];

    /**
     * @var list<string>
     */
    private const SCALES = [
        '',
        'هزار',
        'میلیون',
        'میلیارد',
        'تریلیون',
    ];

    public static function convert(string|int|float $amount, bool $withRial = true): string
    {
        $normalized = Money::normalize(is_string($amount) ? $amount : (string) $amount);

        if ($normalized === '' || ! is_numeric($normalized)) {
            $normalized = '0';
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');

        if (str_contains($normalized, '.')) {
            $normalized = explode('.', $normalized, 2)[0];
        }

        $normalized = ltrim($normalized, '0') ?: '0';

        if ($normalized === '0') {
            $words = 'صفر';
        } else {
            $words = self::convertInteger($normalized);
        }

        if ($negative && $normalized !== '0') {
            $words = 'منفی '.$words;
        }

        if ($withRial) {
            $words .= ' ریال';
        }

        return $words;
    }

    private static function convertInteger(string $number): string
    {
        $chunks = [];

        while ($number !== '0' && $number !== '') {
            $chunk = substr($number, -3);
            $number = substr($number, 0, -3) ?: '0';
            $chunks[] = (int) $chunk;
        }

        $parts = [];

        foreach ($chunks as $index => $chunk) {
            if ($chunk === 0) {
                continue;
            }

            $chunkWords = self::convertBelowThousand($chunk);
            $scale = self::SCALES[$index] ?? '';

            $parts[] = $scale !== '' ? $chunkWords.' '.$scale : $chunkWords;
        }

        return implode(' و ', array_reverse($parts));
    }

    private static function convertBelowThousand(int $number): string
    {
        $parts = [];

        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;

        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        if ($remainder >= 10 && $remainder <= 19) {
            $parts[] = self::TEENS[$remainder - 10];
        } else {
            $tens = intdiv($remainder, 10);
            $ones = $remainder % 10;

            if ($tens > 0) {
                $parts[] = self::TENS[$tens];
            }

            if ($ones > 0) {
                $parts[] = self::ONES[$ones];
            }
        }

        return implode(' و ', $parts);
    }
}
