<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Morilog\Jalali\Jalalian;
use Throwable;

class LocaleDate
{
    public static function usesJalali(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === 'fa';
    }

    public static function isRtl(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === 'fa';
    }

    public static function direction(?string $locale = null): string
    {
        return self::isRtl($locale) ? 'rtl' : 'ltr';
    }

    public static function formatDate(DateTimeInterface|string|null $date, ?string $locale = null): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($date))
            : Carbon::parse($date);

        if (self::usesJalali($locale)) {
            return Jalalian::fromDateTime($carbon)->format('Y/m/d');
        }

        return $carbon->format('Y-m-d');
    }

    public static function formatDateTime(DateTimeInterface|string|null $date, ?string $locale = null): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($date))
            : Carbon::parse($date);

        if (self::usesJalali($locale)) {
            return Jalalian::fromDateTime($carbon)->format('Y/m/d H:i');
        }

        return $carbon->format('Y-m-d H:i');
    }

    public static function parseFilterDate(?string $value, ?string $locale = null): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            if (self::usesJalali($locale)) {
                if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $value, $matches) !== 1) {
                    return null;
                }

                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];

                // Storage/ISO dates are Gregorian (year >= 1700). User-facing Jalali years are smaller.
                if ($year >= 1700) {
                    return Carbon::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day))->startOfDay();
                }

                return Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $year, $month, $day))
                    ->toCarbon()
                    ->startOfDay();
            }

            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function filterPlaceholder(?string $locale = null): string
    {
        return self::usesJalali($locale) ? '1403/01/01' : 'YYYY-MM-DD';
    }

    public static function filterInputType(?string $locale = null): string
    {
        return self::usesJalali($locale) ? 'text' : 'date';
    }

    public static function formatInput(DateTimeInterface|string|null $date, ?string $locale = null): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($date))
            : Carbon::parse($date);

        if (self::usesJalali($locale)) {
            return Jalalian::fromDateTime($carbon)->format('Y/m/d');
        }

        return $carbon->format('Y-m-d');
    }

    public static function toStorageDate(?string $value, ?string $locale = null): ?string
    {
        return self::parseFilterDate($value, $locale)?->toDateString();
    }

    /**
     * Parse SMS-style Jalali short dates near a reference day (default: today).
     *
     * Accepts full Y/m/d, Gregorian Y-m-d, d/m/Y, d/m/yy, y/m/d (SMS), and d/m (day/month).
     * Optional time suffix like -16:18 or " 16:18" is ignored for the storage date.
     * Two-digit years and missing years resolve to the candidate closest to $reference.
     */
    public static function toStorageDateNearToday(
        ?string $value,
        ?CarbonInterface $reference = null,
        ?string $locale = null,
    ): ?string {
        return self::parseNearTodayDate($value, $reference, $locale)?->toDateString();
    }

    /**
     * Parse SMS date (and optional time) for chronological sorting.
     */
    public static function parseNearTodayDateTime(
        ?string $value,
        ?CarbonInterface $reference = null,
        ?string $locale = null,
    ): ?CarbonInterface {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalizeDateDigits(trim($value));

        if ($normalized === '') {
            return null;
        }

        [$datePart, $hour, $minute, $second] = self::splitDateAndTime($normalized);
        $date = self::parseNearTodayDate($datePart, $reference, $locale);

        if ($date === null) {
            return null;
        }

        return $date->copy()->setTime($hour, $minute, $second);
    }

    public static function parseNearTodayDate(
        ?string $value,
        ?CarbonInterface $reference = null,
        ?string $locale = null,
    ): ?CarbonInterface {
        if ($value === null) {
            return null;
        }

        $value = self::normalizeDateDigits(trim($value));

        if ($value === '') {
            return null;
        }

        [$value] = self::splitDateAndTime($value);

        $strict = self::parseFilterDate($value, $locale);

        if ($strict !== null) {
            return $strict;
        }

        if (! self::usesJalali($locale)) {
            return null;
        }

        $reference ??= now()->startOfDay();
        $reference = $reference->copy()->startOfDay();

        try {
            if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2}|\d{4})$/', $value, $matches) === 1) {
                $first = (int) $matches[1];
                $second = (int) $matches[2];
                $yearPart = $matches[3];

                if (strlen($yearPart) === 4) {
                    $year = (int) $yearPart;

                    if ($year >= 1700) {
                        return null;
                    }

                    // Full Jalali with day/month/year order (rare in SMS) vs year-first handled by parseFilterDate.
                    return Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $year, $second, $first))
                        ->toCarbon()
                        ->startOfDay();
                }

                // Short form dd/mm/yy (02/08/05) and bank SMS yy/mm/dd (05/08/02); pick closest to today.
                $dayMonthYear = self::bestTwoDigitYearDate($first, $second, (int) $yearPart, $reference);
                $yearMonthDay = self::bestTwoDigitYearDateForYmd($first, $second, (int) $yearPart, $reference);

                return self::closestToReference(array_values(array_filter([
                    $dayMonthYear,
                    $yearMonthDay,
                ])), $reference);
            }

            if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})$/', $value, $matches) === 1) {
                $day = (int) $matches[1];
                $month = (int) $matches[2];

                return self::bestDayMonthDate($day, $month, $reference);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int}
     */
    protected static function splitDateAndTime(string $value): array
    {
        if (preg_match('/^(.+?)(?:[\sT]|-)(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches) === 1) {
            return [
                rtrim($matches[1], " \t"),
                (int) $matches[2],
                (int) $matches[3],
                isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0,
            ];
        }

        return [$value, 0, 0, 0];
    }

    protected static function bestTwoDigitYearDateForYmd(
        int $twoDigitYear,
        int $month,
        int $day,
        CarbonInterface $reference,
    ): ?CarbonInterface {
        $twoDigitYear = $twoDigitYear % 100;
        $candidates = [];

        foreach ([13, 14] as $century) {
            $year = ($century * 100) + $twoDigitYear;
            $carbon = self::tryJalaliDay($year, $month, $day);

            if ($carbon !== null) {
                $candidates[] = $carbon;
            }
        }

        return self::closestToReference($candidates, $reference);
    }

    protected static function normalizeDateDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);

        return str_replace($arabic, $english, $value);
    }

    protected static function bestTwoDigitYearDate(
        int $day,
        int $month,
        int $twoDigitYear,
        CarbonInterface $reference,
    ): ?CarbonInterface {
        $twoDigitYear = $twoDigitYear % 100;
        $candidates = [];

        foreach ([13, 14] as $century) {
            $year = ($century * 100) + $twoDigitYear;
            $carbon = self::tryJalaliDay($year, $month, $day);

            if ($carbon !== null) {
                $candidates[] = $carbon;
            }
        }

        return self::closestToReference($candidates, $reference);
    }

    protected static function bestDayMonthDate(
        int $day,
        int $month,
        CarbonInterface $reference,
    ): ?CarbonInterface {
        $jalaliRef = Jalalian::fromDateTime($reference);
        $candidates = [];

        foreach ([$jalaliRef->getYear() - 1, $jalaliRef->getYear(), $jalaliRef->getYear() + 1] as $year) {
            $carbon = self::tryJalaliDay($year, $month, $day);

            if ($carbon !== null) {
                $candidates[] = $carbon;
            }
        }

        $withinTwoDays = array_values(array_filter(
            $candidates,
            fn (CarbonInterface $candidate): bool => (int) abs($candidate->diffInDays($reference)) <= 2,
        ));

        if ($withinTwoDays !== []) {
            return self::closestToReference($withinTwoDays, $reference);
        }

        return self::closestToReference($candidates, $reference);
    }

    protected static function tryJalaliDay(int $year, int $month, int $day): ?CarbonInterface
    {
        try {
            return Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $year, $month, $day))
                ->toCarbon()
                ->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<CarbonInterface>  $candidates
     */
    protected static function closestToReference(array $candidates, CarbonInterface $reference): ?CarbonInterface
    {
        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            function (CarbonInterface $a, CarbonInterface $b) use ($reference): int {
                $diffA = abs($a->diffInDays($reference));
                $diffB = abs($b->diffInDays($reference));

                return $diffA <=> $diffB;
            },
        );

        return $candidates[0];
    }
}
