<?php

namespace App\Services\Imports;

use App\Services\Imports\Concerns\ParsesBankStatementCsv;
use InvalidArgumentException;
use RuntimeException;

/**
 * Parses Bank Resalat account-statement CSV exports (گردش حساب).
 *
 * Expected columns (Persian headers, any order):
 * کد شعبه، شعبه، تاریخ، ساعت، سند، شماره قبض، شماره چک، توضیحات، برداشت، واریز، مانده، یادداشت
 */
class ResalatStatementCsvParser
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
            throw new InvalidArgumentException('CSV file not readable.');
        }

        $raw = file_get_contents($absolutePath);

        if ($raw === false || $raw === '') {
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $raw = $this->normalizeEncoding($raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            throw new InvalidArgumentException('CSV file has no rows.');
        }

        $header = str_getcsv(array_shift($lines), ',', '"', '\\');
        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);
        $map = $this->mapColumns($header);

        $rows = [];

        foreach ($lines as $line) {
            $cells = str_getcsv($line, ',', '"', '\\');

            if ($cells === [null] || $cells === false) {
                continue;
            }

            $get = function (string $key) use ($cells, $map): string {
                $index = $map[$key] ?? null;

                if ($index === null || ! array_key_exists($index, $cells)) {
                    return '';
                }

                return trim((string) $cells[$index]);
            };

            $credit = $this->normalizeAmount($get('credit'));
            $debit = $this->normalizeAmount($get('debit'));
            $balance = $this->normalizeAmount($get('balance'));
            $desc = $get('description');
            $noteExtra = $get('note');
            $serial = $this->normalizeDigits($get('serial'));
            $date = $this->normalizeDigits($get('date'));
            $time = $this->normalizeDigits($get('time'));

            if ($credit === '0' && $debit === '0') {
                continue;
            }

            if ($credit !== '0' && $debit !== '0') {
                throw new RuntimeException('CSV row has both credit and debit amounts.');
            }

            $isCredit = bccomp($credit, '0', 18) === 1;
            $amount = $isCredit ? $credit : $debit;
            $type = $isCredit ? 'income' : 'expense';
            $transactionDate = trim($date.($time !== '' ? ' '.$time : ''));
            $combinedNote = trim($desc.($noteExtra !== '' ? ' '.$noteExtra : ''));

            $rows[] = [
                'type' => $type,
                'amount' => $amount,
                'expected_balance' => $balance,
                'note' => $combinedNote,
                'reference_number' => $serial,
                'transaction_date' => $transactionDate,
                'party' => '',
                'is_fee' => $this->looksLikeFee($combinedNote),
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('CSV file has no transaction rows.');
        }

        return [
            'account_number_hint' => $this->accountHintFromFilename($originalFilename),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    protected function mapColumns(array $header): array
    {
        return $this->mapHeaderColumns($header, [
            'balance' => ['مانده', 'balance'],
            'credit' => ['واریز', 'credit'],
            'debit' => ['برداشت', 'debit'],
            'description' => ['توضیحات', 'شرح', 'description', 'desc'],
            'note' => ['یادداشت', 'note'],
            'serial' => ['سند', 'شماره سند', 'serial'],
            'time' => ['ساعت', 'زمان', 'time'],
            'date' => ['تاریخ', 'date'],
        ], ['balance', 'credit', 'debit', 'description', 'date']);
    }
}
