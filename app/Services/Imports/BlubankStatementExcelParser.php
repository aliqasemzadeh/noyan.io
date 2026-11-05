<?php

namespace App\Services\Imports;

use App\Services\Imports\Concerns\ParsesBankStatementCsv;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Parses Blu Bank (بلو بانک) account-statement Excel exports.
 *
 * Sheet name is typically "transaction". Header row sits below summary
 * metadata and includes: مانده (ریال)، برداشت (ریال)، واریز (ریال)،
 * نوع تراکنش، شرح سند، شماره سند، تاریخ، ردیف.
 */
class BlubankStatementExcelParser
{
    use ParsesBankStatementCsv;

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
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new InvalidArgumentException('Excel file not readable.');
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Excel file could not be opened: '.$exception->getMessage(), 0, $exception);
        }

        $sheet = $spreadsheet->getSheetByName('transaction') ?? $spreadsheet->getActiveSheet();
        [$headerRow, $header] = $this->findHeaderRow($sheet);
        $map = $this->mapColumns($header);
        $highestRow = (int) $sheet->getHighestDataRow();
        $rows = [];

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $get = function (string $key) use ($sheet, $map, $row): string {
                $index = $map[$key] ?? null;

                if ($index === null) {
                    return '';
                }

                return $this->cellString($sheet, $row, $index);
            };

            $credit = $this->normalizeAmount($get('credit'));
            $debit = $this->normalizeAmount($get('debit'));
            $balance = $this->normalizeAmount($get('balance'));
            $desc = $get('description');
            $txnType = $get('txn_type');
            $serial = $this->normalizeDigits($get('serial'));
            $date = $this->normalizeDigits($get('date'));

            if ($credit === '0' && $debit === '0') {
                continue;
            }

            if ($credit !== '0' && $debit !== '0') {
                throw new RuntimeException('Excel row has both credit and debit amounts.');
            }

            $isCredit = bccomp($credit, '0', 18) === 1;
            $amount = $isCredit ? $credit : $debit;
            $type = $isCredit ? 'income' : 'expense';
            $combinedNote = trim($txnType !== '' && $txnType !== $desc
                ? $txnType.' — '.$desc
                : ($desc !== '' ? $desc : $txnType));

            $rows[] = [
                'type' => $type,
                'amount' => $amount,
                'expected_balance' => $balance,
                'note' => $combinedNote,
                'reference_number' => $serial,
                'transaction_date' => $date,
                'party' => '',
                'is_fee' => $this->looksLikeFee($combinedNote, $txnType),
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Excel file has no transaction rows.');
        }

        return [
            'account_number_hint' => $this->accountHintFromSheet($sheet)
                ?? $this->accountHintFromFilename($originalFilename),
            'rows' => $rows,
        ];
    }

    public function looksLike(string $absolutePath): bool
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'xls', 'xlsm'], true)) {
            return false;
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getSheetByName('transaction') ?? $spreadsheet->getActiveSheet();
            [, $header] = $this->findHeaderRow($sheet);

            return $this->headersLookLikeBlubank($header);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $header
     */
    public function headersLookLikeBlubank(array $header): bool
    {
        $joined = implode('|', $header);

        return str_contains($joined, 'برداشت')
            && str_contains($joined, 'واریز')
            && str_contains($joined, 'مانده')
            && (str_contains($joined, 'ریال') || str_contains($joined, 'شرح سند') || str_contains($joined, 'نوع تراکنش'));
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    protected function findHeaderRow(Worksheet $sheet): array
    {
        $highestRow = min((int) $sheet->getHighestDataRow(), 40);
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= $highestRow; $row++) {
            /** @var array<int, string> $headerByColumn 1-based spreadsheet column => label */
            $headerByColumn = [];

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $headerByColumn[$col] = $this->normalizeHeader($this->cellString($sheet, $row, $col));
            }

            if ($this->headersLookLikeBlubank(array_values($headerByColumn))) {
                return [$row, $headerByColumn];
            }
        }

        throw new InvalidArgumentException('Blu Bank statement header row not found.');
    }

    /**
     * @param  array<int, string>  $header 1-based spreadsheet column => label
     * @return array<string, int>
     */
    protected function mapColumns(array $header): array
    {
        return $this->mapHeaderColumns($header, [
            'balance' => ['مانده', 'balance'],
            'credit' => ['واریز', 'credit'],
            'debit' => ['برداشت', 'debit'],
            'txn_type' => ['نوع تراکنش', 'نوع'],
            'description' => ['شرح سند', 'شرح', 'description', 'desc'],
            'serial' => ['شماره سند', 'سند', 'serial'],
            'date' => ['تاریخ', 'date'],
        ], ['balance', 'credit', 'debit', 'description', 'date']);
    }

    protected function accountHintFromSheet(Worksheet $sheet): ?string
    {
        $highestRow = min((int) $sheet->getHighestDataRow(), 20);
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $this->normalizeDigits($this->cellString($sheet, $row, $col));

                if ($value === '') {
                    continue;
                }

                if (preg_match('/IR\d{24}/i', $value, $matches) === 1) {
                    return strtoupper($matches[0]);
                }

                if (preg_match('/شماره\s*سپرده\s*:?\s*(\d{10,20})/u', $value, $matches) === 1) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    protected function cellString(Worksheet $sheet, int $row, int $column): string
    {
        $value = $sheet->getCell([$column, $row])->getCalculatedValue();

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return sprintf('%.0f', $value);
        }

        return trim((string) $value);
    }
}
