<?php

namespace App\Actions\Ledger\Concerns;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\DetailType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\GlAccount;
use App\Models\Accounting\GlDetail;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Party;
use App\Models\Accounting\Project;
use App\Models\Category;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

trait ValidatesJournalLines
{
    /**
     * @param  list<array<string, mixed>>  $linesData
     * @return list<array{
     *     category_id: int|null,
     *     gl_account_id: int|null,
     *     party_id: int|null,
     *     account_id: int|null,
     *     cost_center_id: int|null,
     *     project_id: int|null,
     *     details: list<array{level: int, gl_detail_id: int}>,
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

            $glAccountId = isset($line['gl_account_id']) && filled($line['gl_account_id'])
                ? (int) $line['gl_account_id']
                : null;

            if ($categoryId === null && $glAccountId === null) {
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

            $glAccount = null;

            if ($glAccountId !== null) {
                $glAccount = $this->assertGlAccountPostable($businessId, $glAccountId, $index);
            } elseif ($categoryId !== null) {
                $this->assertCategoryBelongsToBusiness($businessId, $categoryId, $index);
                $category = Category::query()->find($categoryId);
                if ($category?->gl_account_id) {
                $glAccount = GlAccount::query()
                    ->with('detailLevels')
                    ->where('business_id', $businessId)
                    ->whereKey($category->gl_account_id)
                    ->first();
                    $glAccountId = $glAccount?->id;
                }
            }

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

            $detailRows = $this->resolveLineDetails(
                $businessId,
                $line,
                $glAccount,
                $partyId,
                $accountId,
                $costCenterId,
                $projectId,
                $index,
            );

            [$partyId, $accountId, $costCenterId, $projectId] = $this->dualFillLegacyFksFromDetails(
                $businessId,
                $partyId,
                $accountId,
                $costCenterId,
                $projectId,
                array_column($detailRows, 'gl_detail_id'),
            );

            $description = filled($line['description'] ?? null)
                ? trim((string) $line['description'])
                : null;

            $normalized[] = [
                'category_id' => $categoryId,
                'gl_account_id' => $glAccountId,
                'party_id' => $partyId,
                'account_id' => $accountId,
                'cost_center_id' => $costCenterId,
                'project_id' => $projectId,
                'details' => $detailRows,
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

    protected function assertGlAccountPostable(int $businessId, int $glAccountId, int $index): GlAccount
    {
        $account = GlAccount::query()
            ->with('detailLevels')
            ->where('business_id', $businessId)
            ->whereKey($glAccountId)
            ->first();

        if ($account === null || ! $account->is_active) {
            throw ValidationException::withMessages([
                "lines.{$index}.gl_account_id" => [__('general.journal_gl_account_invalid')],
            ]);
        }

        if ($account->level !== AccountLevel::Subsidiary || ! $account->is_postable) {
            throw ValidationException::withMessages([
                "lines.{$index}.gl_account_id" => [__('general.journal_gl_account_must_be_postable')],
            ]);
        }

        return $account;
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
     * @param  array<string, mixed>  $line
     * @return list<array{level: int, gl_detail_id: int}>
     */
    protected function resolveLineDetails(
        int $businessId,
        array $line,
        ?GlAccount $glAccount,
        ?int $partyId,
        ?int $accountId,
        ?int $costCenterId,
        ?int $projectId,
        int $index,
    ): array {
        if ($glAccount === null) {
            return [];
        }

        $rows = [];
        $legacyConsumed = [];

        foreach ($glAccount->detailLevels as $requirement) {
            $level = (int) $requirement->level;
            $type = $requirement->detail_type;
            $explicit = $this->explicitDetailId($line, $level);

            if ($explicit !== null && $explicit !== '' && $explicit !== false) {
                $detailId = $this->assertExplicitDetail($businessId, $explicit, $type, $index, $level);
                $legacyConsumed[$type->value] = true;
            } elseif (! isset($legacyConsumed[$type->value])) {
                $legacyConsumed[$type->value] = true;
                $detailId = $this->detailIdFromLegacyFk(
                    $businessId,
                    $partyId,
                    $accountId,
                    $costCenterId,
                    $projectId,
                    $type,
                );
            } else {
                $detailId = null;
            }

            if ($detailId === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.details.{$level}" => [__('general.journal_detail_level_required', [
                        'level' => $level,
                        'type' => $type->label(),
                    ])],
                ]);
            }

            $rows[] = [
                'level' => $level,
                'gl_detail_id' => $detailId,
            ];
        }

        return $rows;
    }

    protected function explicitDetailId(array $line, int $level): mixed
    {
        $details = $line['details'] ?? null;

        if (! is_array($details)) {
            return null;
        }

        if (array_key_exists($level, $details) && ! is_array($details[$level])) {
            return $details[$level];
        }

        foreach ($details as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ((int) ($row['level'] ?? 0) === $level) {
                return $row['gl_detail_id'] ?? null;
            }
        }

        return null;
    }

    protected function assertExplicitDetail(
        int $businessId,
        mixed $explicitDetailId,
        DetailType $requiredType,
        int $index,
        int $level,
    ): int {
        $detailId = (int) $explicitDetailId;
        $detail = GlDetail::query()
            ->where('business_id', $businessId)
            ->whereKey($detailId)
            ->first();

        if ($detail === null) {
            throw ValidationException::withMessages([
                "lines.{$index}.details.{$level}" => [__('general.journal_detail_invalid')],
            ]);
        }

        if ($detail->type !== $requiredType) {
            throw ValidationException::withMessages([
                "lines.{$index}.details.{$level}" => [__('general.journal_detail_type_mismatch')],
            ]);
        }

        return $detailId;
    }

    protected function detailIdFromLegacyFk(
        int $businessId,
        ?int $partyId,
        ?int $accountId,
        ?int $costCenterId,
        ?int $projectId,
        ?DetailType $type,
    ): ?int {
        $candidates = [
            DetailType::Party->value => $partyId,
            DetailType::Account->value => $accountId,
            DetailType::CostCenter->value => $costCenterId,
            DetailType::Project->value => $projectId,
        ];

        if ($type !== null) {
            $id = $candidates[$type->value] ?? null;

            if ($id === null) {
                return null;
            }

            return GlDetail::query()
                ->where('business_id', $businessId)
                ->where('type', $type->value)
                ->where('detailable_id', $id)
                ->where('detailable_type', $this->morphClassForDetailType($type))
                ->value('id');
        }

        foreach ($candidates as $typeValue => $id) {
            if ($id === null) {
                continue;
            }

            $detailType = DetailType::from($typeValue);
            $detailId = GlDetail::query()
                ->where('business_id', $businessId)
                ->where('type', $typeValue)
                ->where('detailable_id', $id)
                ->where('detailable_type', $this->morphClassForDetailType($detailType))
                ->value('id');

            if ($detailId !== null) {
                return (int) $detailId;
            }
        }

        return null;
    }

    protected function morphClassForDetailType(DetailType $type): string
    {
        return match ($type) {
            DetailType::Party => (new Party)->getMorphClass(),
            DetailType::Account => (new Account)->getMorphClass(),
            DetailType::CostCenter => (new CostCenter)->getMorphClass(),
            DetailType::Project => (new Project)->getMorphClass(),
        };
    }

    /**
     * @param  list<int>  $detailIds
     * @return array{0: int|null, 1: int|null, 2: int|null, 3: int|null}
     */
    protected function dualFillLegacyFksFromDetails(
        int $businessId,
        ?int $partyId,
        ?int $accountId,
        ?int $costCenterId,
        ?int $projectId,
        array $detailIds,
    ): array {
        foreach ($detailIds as $detailId) {
            if ($detailId === 0) {
                continue;
            }

            $detail = GlDetail::query()
                ->where('business_id', $businessId)
                ->whereKey($detailId)
                ->first();

            if ($detail === null) {
                continue;
            }

            match ($detail->type) {
                DetailType::Party => $partyId ??= (int) $detail->detailable_id,
                DetailType::Account => $accountId ??= (int) $detail->detailable_id,
                DetailType::CostCenter => $costCenterId ??= (int) $detail->detailable_id,
                DetailType::Project => $projectId ??= (int) $detail->detailable_id,
            };
        }

        return [$partyId, $accountId, $costCenterId, $projectId];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function persistJournalLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $line) {
            $details = $line['details'] ?? [];
            unset($line['details']);

            $created = $entry->lines()->create($line);

            foreach ($details as $detail) {
                $created->details()->create([
                    'level' => $detail['level'],
                    'gl_detail_id' => $detail['gl_detail_id'],
                ]);
            }
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
        $value = Money::normalize($value);

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
