<?php

namespace App\Actions\Accounting;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\AccountNature;
use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\StatementType;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class SeedBusinessChartOfAccountsAction
{
    /**
     * @return list<GlAccount>
     */
    public function handle(Business $business, bool $force = false): array
    {
        return DB::transaction(function () use ($business, $force): array {
            $existing = GlAccount::query()
                ->where('business_id', $business->id)
                ->exists();

            if ($existing && ! $force) {
                return GlAccount::query()
                    ->where('business_id', $business->id)
                    ->orderBy('full_code')
                    ->get()
                    ->all();
            }

            $created = [];

            foreach ($this->definitions() as $index => $group) {
                $created = array_merge(
                    $created,
                    $this->seedNode($business, $group, null, AccountLevel::Group, $index),
                );
            }

            return $created;
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     nature: AccountNature,
     *     statement_type: StatementType,
     *     system_key?: string|null,
     *     requires_detail_level_4_type?: DetailType|null,
     *     children?: list<array<string, mixed>>
     * }  $node
     * @return list<GlAccount>
     */
    protected function seedNode(
        Business $business,
        array $node,
        ?GlAccount $parent,
        AccountLevel $level,
        int $sortOrder,
    ): array {
        $fullCode = $parent === null ? $node['code'] : $parent->full_code.$node['code'];
        $isSubsidiary = $level === AccountLevel::Subsidiary;

        $account = GlAccount::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'full_code' => $fullCode,
            ],
            [
                'parent_id' => $parent?->id,
                'level' => $level,
                'code' => $node['code'],
                'name' => $node['name'],
                'nature' => $node['nature'],
                'statement_type' => $node['statement_type'],
                'is_postable' => $isSubsidiary,
                'is_system' => true,
                'system_key' => $node['system_key'] ?? null,
                'requires_detail_level_4_type' => $node['requires_detail_level_4_type'] ?? null,
                'requires_detail_level_5_type' => $node['requires_detail_level_5_type'] ?? null,
                'requires_detail_level_6_type' => $node['requires_detail_level_6_type'] ?? null,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );

        $created = [$account];
        $childLevel = $level->childLevel();

        if ($childLevel === null) {
            return $created;
        }

        foreach ($node['children'] ?? [] as $childIndex => $child) {
            $created = array_merge(
                $created,
                $this->seedNode($business, $child, $account, $childLevel, $childIndex),
            );
        }

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function definitions(): array
    {
        return [
            [
                'code' => '1',
                'name' => 'Assets',
                'nature' => AccountNature::Debit,
                'statement_type' => StatementType::BalanceSheet,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'Current assets',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Cash and bank',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'cash_and_bank',
                                'requires_detail_level_4_type' => DetailType::Account,
                            ],
                            [
                                'code' => '02',
                                'name' => 'Accounts receivable',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'accounts_receivable',
                                'requires_detail_level_4_type' => DetailType::Party,
                            ],
                            [
                                'code' => '03',
                                'name' => 'Inventory',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'inventory',
                            ],
                            [
                                'code' => '04',
                                'name' => 'VAT receivable',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'vat_receivable',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'Non-current assets',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Property, plant and equipment',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'ppe',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'code' => '2',
                'name' => 'Liabilities',
                'nature' => AccountNature::Credit,
                'statement_type' => StatementType::BalanceSheet,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'Current liabilities',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Accounts payable',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'accounts_payable',
                                'requires_detail_level_4_type' => DetailType::Party,
                            ],
                            [
                                'code' => '02',
                                'name' => 'VAT payable',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'vat_payable',
                            ],
                            [
                                'code' => '03',
                                'name' => 'Short-term loans',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'short_term_loans',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'Non-current liabilities',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Long-term loans',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'long_term_loans',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'code' => '3',
                'name' => 'Equity',
                'nature' => AccountNature::Credit,
                'statement_type' => StatementType::BalanceSheet,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'Capital and reserves',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Owner capital',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'owner_capital',
                            ],
                            [
                                'code' => '02',
                                'name' => 'Retained earnings',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'retained_earnings',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'code' => '4',
                'name' => 'Revenue',
                'nature' => AccountNature::Credit,
                'statement_type' => StatementType::IncomeStatement,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'Operating revenue',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Sales',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'sales',
                            ],
                            [
                                'code' => '02',
                                'name' => 'Services',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'services',
                            ],
                            [
                                'code' => '03',
                                'name' => 'Sales discounts',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'sales_discount',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'Other income',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Foreign exchange gain',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'fx_gain',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'code' => '5',
                'name' => 'Expenses',
                'nature' => AccountNature::Debit,
                'statement_type' => StatementType::IncomeStatement,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'Cost of sales',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Cost of goods sold',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'cogs',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'Operating expenses',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Rent expense',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'rent_expense',
                            ],
                            [
                                'code' => '02',
                                'name' => 'Payroll expense',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'payroll_expense',
                            ],
                            [
                                'code' => '03',
                                'name' => 'Bank fees',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'bank_fees',
                            ],
                        ],
                    ],
                    [
                        'code' => '3',
                        'name' => 'Other expenses',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'Foreign exchange loss',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'fx_loss',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
