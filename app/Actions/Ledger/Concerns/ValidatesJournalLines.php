<?php

namespace App\Actions\Ledger\Concerns;

use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Party;
use App\Models\Accounting\Project;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

trait ValidatesJournalLines
{
    /**
     * @param  list<array<string, mixed>>  $linesData
     * @return list<array{
     *     category_id: int,
     *     party_id: int|null,
     *     account_id: int|null,
     *     cost_center_id: int|null,
     *     project_id: int|null,
     *     debit: string,
     *     credit: string,
     *     description: string|null
     * }>
     */
    protected function normalizeAndValidateLines(int $businessId, array $linesData): array
    {
        $normalized = [];

        foreach (array_values($linesData) as $index => $line) {
            $categoryId = isset($line['category_id']) && filled($line['category_id'])
                ? (int) $line['category_id']
                : null;

            if ($categoryId === null) {
                continue;
            }

            $debit = $this->normalizeAmount((string) ($line['debit'] ?? '0'));
            $credit = $this->normalizeAmount((string) ($line['credit'] ?? '0'));

            $debitPositive = bccomp($debit, '0', 4) === 1;
            $creditPositive = bccomp($credit, '0', 4) === 1;

            if (! $debitPositive && ! $creditPositive) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [__('general.journal_line_amount_required')],
                ]);
            }

            if ($debitPositive && $creditPositive) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [__('general.journal_line_debit_credit_exclusive')],
                ]);
            }

            $this->assertCategoryBelongsToBusiness($businessId, $categoryId, $index);

            $partyId = $this->nullableBelongsToBusiness(
                Party::class,
                $businessId,
                $line['party_id'] ?? null,
                "lines.{$index}.party_id",
                __('general.journal_party_invalid'),
            );

            $accountId = $this->nullableBelongsToBusiness(
                Account::class,
                $businessId,
                $line['account_id'] ?? null,
                "lines.{$index}.account_id",
                __('general.journal_account_invalid'),
            );

            $costCenterId = $this->nullableBelongsToBusiness(
                CostCenter::class,
                $businessId,
                $line['cost_center_id'] ?? null,
                "lines.{$index}.cost_center_id",
                __('general.journal_cost_center_invalid'),
            );

            $projectId = $this->nullableBelongsToBusiness(
                Project::class,
                $businessId,
                $line['project_id'] ?? null,
                "lines.{$index}.project_id",
                __('general.journal_project_invalid'),
            );

            $description = filled($line['description'] ?? null)
                ? trim((string) $line['description'])
                : null;

            $normalized[] = [
                'category_id' => $categoryId,
                'party_id' => $partyId,
                'account_id' => $accountId,
                'cost_center_id' => $costCenterId,
                'project_id' => $projectId,
                'debit' => $debitPositive ? $debit : '0',
                'credit' => $creditPositive ? $credit : '0',
                'description' => $description,
            ];
        }

        if (count($normalized) < 2) {
            throw ValidationException::withMessages([
                'lines' => [__('general.journal_lines_minimum')],
            ]);
        }

        $this->assertBalanced($normalized);

        return $normalized;
    }

    /**
     * @param  list<array{debit: string, credit: string}>  $lines
     */
    protected function assertBalanced(array $lines): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, $line['debit'], 4);
            $totalCredit = bcadd($totalCredit, $line['credit'], 4);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw ValidationException::withMessages([
                'lines' => [__('general.journal_entry_out_of_balance')],
            ]);
        }
    }

    protected function resolveOpenFiscalYear(int $businessId, int $fiscalYearId, string $entryDate): FiscalYear
    {
        $fiscalYear = FiscalYear::query()
            ->where('business_id', $businessId)
            ->whereKey($fiscalYearId)
            ->first();

        if ($fiscalYear === null) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => [__('general.journal_fiscal_year_invalid')],
            ]);
        }

        if ($fiscalYear->is_closed) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => [__('general.journal_fiscal_year_closed')],
            ]);
        }

        if (! $fiscalYear->containsDate($entryDate)) {
            throw ValidationException::withMessages([
                'entry_date' => [__('general.journal_entry_date_outside_fiscal_year')],
            ]);
        }

        return $fiscalYear;
    }

    protected function assertCategoryBelongsToBusiness(int $businessId, int $categoryId, int $index): void
    {
        $category = Category::query()
            ->availableToBusiness($businessId)
            ->whereKey($categoryId)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                "lines.{$index}.category_id" => [__('general.journal_category_invalid')],
            ]);
        }

        if (! in_array($category->type, CategoryType::accountingCases(), true)) {
            throw ValidationException::withMessages([
                "lines.{$index}.category_id" => [__('general.journal_category_invalid')],
            ]);
        }

        if (! $category->isLeaf()) {
            throw ValidationException::withMessages([
                "lines.{$index}.category_id" => [__('general.journal_category_must_be_leaf')],
            ]);
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function nullableBelongsToBusiness(
        string $modelClass,
        int $businessId,
        mixed $id,
        string $errorKey,
        string $message,
    ): ?int {
        if ($id === null || $id === '' || $id === false) {
            return null;
        }

        $resolvedId = (int) $id;

        $exists = $modelClass::query()
            ->where('business_id', $businessId)
            ->whereKey($resolvedId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $errorKey => [$message],
            ]);
        }

        return $resolvedId;
    }

    protected function normalizeAmount(string $value): string
    {
        $value = trim(str_replace(',', '', $value));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        if (bccomp($value, '0', 4) === -1) {
            throw ValidationException::withMessages([
                'lines' => [__('general.journal_amount_must_be_non_negative')],
            ]);
        }

        return bcadd($value, '0', 4);
    }
}
