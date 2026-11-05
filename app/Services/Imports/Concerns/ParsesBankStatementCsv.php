<?php

namespace App\Services\Imports\Concerns;

use InvalidArgumentException;

trait ParsesBankStatementCsv
{
    protected function normalizeEncoding(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
            if (is_string($converted) && $converted !== '') {
                $raw = $converted;
            }
        }

        return $raw;
    }

    protected function normalizeHeader(string $value): string
    {
        $value = $this->normalizeDigits($value);
        $value = str_replace(["\u{200c}", "\u{00a0}"], ' ', $value);

        return trim(mb_strtolower($value));
    }

    protected function normalizeAmount(string $value): string
    {
        $value = $this->normalizeDigits($value);
        $value = str_replace([',', '٬', ' ', '،'], '', $value);

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 0);
    }

    protected function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($persian, $latin, $value);
    }

    protected function looksLikeFee(string $description, string $party = ''): bool
    {
        $haystack = mb_strtolower($description.' '.$party);

        return str_contains($haystack, 'کارمزد') || str_contains($haystack, 'كارمزد');
    }

    protected function accountHintFromFilename(?string $filename): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = $this->normalizeDigits($base);

        // Dotted Resalat-style: Invoice_10.3900820.1_14050814 or 10.3900820.1.csv
        if (preg_match('/(\d{1,4}(?:\.\d+){1,4})/', $base, $matches) === 1) {
            return $matches[1];
        }

        // Plain digits (Mellat-style): 1266272158-sample.csv
        if (preg_match('/(\d{6,20})/', $base, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $header
     * @param  array<string, list<string>>  $aliases
     * @param  list<string>  $required
     * @return array<string, int>
     */
    protected function mapHeaderColumns(array $header, array $aliases, array $required): array
    {
        $map = [];

        foreach ($aliases as $key => $names) {
            foreach ($header as $index => $label) {
                foreach ($names as $name) {
                    $normalizedName = $this->normalizeHeader($name);

                    if ($label === $normalizedName || str_contains($label, $normalizedName)) {
                        $map[$key] = $index;
                        break 2;
                    }
                }
            }
        }

        foreach ($required as $key) {
            if (! isset($map[$key])) {
                throw new InvalidArgumentException("CSV missing required column: {$key}");
            }
        }

        return $map;
    }
}
