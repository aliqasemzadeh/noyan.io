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
                $normalized = str_replace(['-', '.'], '/', $value);

                return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->startOfDay();
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
}
