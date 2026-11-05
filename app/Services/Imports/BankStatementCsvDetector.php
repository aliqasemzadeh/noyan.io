<?php

namespace App\Services\Imports;

use InvalidArgumentException;

class BankStatementCsvDetector
{
    /**
     * @return array{
     *     account_number_hint: string|null,
     *     rows: list<array{
     *         type: string,
     *         amount: string,
     *         expected_balance: string,
     *         note: string,
     *         reference_number: string,
     *         transaction_date: string,
     *         party: string,
     *         is_fee: bool
     *     }>
     * }
     */
    public function parse(string $absolutePath, ?string $originalFilename = null): array
    {
        if ($this->isExcelPath($absolutePath, $originalFilename)) {
            $blubank = app(BlubankStatementExcelParser::class);

            if ($blubank->looksLike($absolutePath)) {
                return $blubank->parse($absolutePath, $originalFilename);
            }

            throw new InvalidArgumentException('Unrecognized bank statement Excel format.');
        }

        $headers = $this->readNormalizedHeaders($absolutePath);

        if ($this->looksLikeResalat($headers)) {
            return app(ResalatStatementCsvParser::class)->parse($absolutePath, $originalFilename);
        }

        if ($this->looksLikeMellat($headers)) {
            return app(MellatStatementCsvParser::class)->parse($absolutePath, $originalFilename);
        }

        throw new InvalidArgumentException('Unrecognized bank statement CSV format.');
    }

    public function looksLikeBankStatement(string $absolutePath, ?string $originalFilename = null): bool
    {
        try {
            if ($this->isExcelPath($absolutePath, $originalFilename)) {
                return app(BlubankStatementExcelParser::class)->looksLike($absolutePath);
            }

            $headers = $this->readNormalizedHeaders($absolutePath);

            return $this->looksLikeResalat($headers) || $this->looksLikeMellat($headers);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    protected function isExcelPath(string $absolutePath, ?string $originalFilename = null): bool
    {
        $candidates = array_filter([
            $originalFilename,
            $absolutePath,
        ]);

        foreach ($candidates as $candidate) {
            $extension = strtolower(pathinfo((string) $candidate, PATHINFO_EXTENSION));

            if (in_array($extension, ['xlsx', 'xls', 'xlsm'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function readNormalizedHeaders(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new InvalidArgumentException('CSV file not readable.');
        }

        $raw = file_get_contents($absolutePath);

        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('CSV file is empty.');
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
            if (is_string($converted) && $converted !== '') {
                $raw = $converted;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            throw new InvalidArgumentException('CSV file has no rows.');
        }

        $header = str_getcsv($lines[0], ',', '"', '\\');

        return array_map(function ($h): string {
            $value = str_replace(["\u{200c}", "\u{00a0}"], ' ', (string) $h);

            return trim(mb_strtolower($value));
        }, $header);
    }

    /**
     * @param  list<string>  $headers
     */
    protected function looksLikeResalat(array $headers): bool
    {
        $joined = implode('|', $headers);

        return str_contains($joined, 'برداشت')
            && str_contains($joined, 'واریز')
            && (str_contains($joined, 'توضیحات') || str_contains($joined, 'سند'));
    }

    /**
     * @param  list<string>  $headers
     */
    protected function looksLikeMellat(array $headers): bool
    {
        $joined = implode('|', $headers);

        return (str_contains($joined, 'بستانکار') || str_contains($joined, 'بدهکار'))
            && (str_contains($joined, 'شرح') || str_contains($joined, 'مانده'));
    }
}
