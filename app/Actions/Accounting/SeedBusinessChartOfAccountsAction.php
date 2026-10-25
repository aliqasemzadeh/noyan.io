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
     * Upsert the standard chart. Safe to run repeatedly — uses business_id + full_code
     * and never creates duplicate rows. Soft-deleted system rows are restored.
     *
     * @return list<GlAccount>
     */
    public function handle(Business $business, bool $force = false): array
    {
        // $force retained for call-site BC; seeding is always an idempotent upsert.
        unset($force);

        return DB::transaction(function () use ($business): array {
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
        $systemKey = $node['system_key'] ?? null;

        if (filled($systemKey)) {
            GlAccount::withTrashed()
                ->where('business_id', $business->id)
                ->where('system_key', $systemKey)
                ->where('full_code', '!=', $fullCode)
                ->update(['system_key' => null]);
        }

        $account = GlAccount::withTrashed()->firstOrNew([
            'business_id' => $business->id,
            'full_code' => $fullCode,
        ]);

        $account->fill([
            'parent_id' => $parent?->id,
            'level' => $level,
            'code' => $node['code'],
            'name' => $node['name'],
            'nature' => $node['nature'],
            'statement_type' => $node['statement_type'],
            'is_postable' => $isSubsidiary,
            'is_system' => true,
            'system_key' => $systemKey,
            'requires_detail_level_4_type' => $node['requires_detail_level_4_type'] ?? null,
            'requires_detail_level_5_type' => $node['requires_detail_level_5_type'] ?? null,
            'requires_detail_level_6_type' => $node['requires_detail_level_6_type'] ?? null,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);

        if ($account->trashed()) {
            $account->restore();
        } else {
            $account->save();
        }

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
                'name' => 'دارایی‌ها',
                'nature' => AccountNature::Debit,
                'statement_type' => StatementType::BalanceSheet,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'دارایی‌های جاری',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'موجودی نقد و بانک',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'cash_and_bank',
                                'requires_detail_level_4_type' => DetailType::Account,
                            ],
                            [
                                'code' => '02',
                                'name' => 'حساب‌های دریافتنی',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'accounts_receivable',
                                'requires_detail_level_4_type' => DetailType::Party,
                            ],
                            [
                                'code' => '03',
                                'name' => 'موجودی کالا',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'inventory',
                            ],
                            [
                                'code' => '04',
                                'name' => 'مالیات بر ارزش افزوده دریافتنی',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'vat_receivable',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'دارایی‌های غیرجاری',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'اموال، ماشین‌آلات و تجهیزات',
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
                'name' => 'بدهی‌ها',
                'nature' => AccountNature::Credit,
                'statement_type' => StatementType::BalanceSheet,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'بدهی‌های جاری',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'حساب‌های پرداختنی',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'accounts_payable',
                                'requires_detail_level_4_type' => DetailType::Party,
                            ],
                            [
                                'code' => '02',
                                'name' => 'مالیات بر ارزش افزوده پرداختنی',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'vat_payable',
                            ],
                            [
                                'code' => '03',
                                'name' => 'وام‌های کوتاه‌مدت',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'short_term_loans',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'بدهی‌های غیرجاری',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'وام‌های بلندمدت',
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
                'name' => 'حقوق صاحبان سهام',
                'nature' => AccountNature::Credit,
                'statement_type' => StatementType::BalanceSheet,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'سرمایه و اندوخته‌ها',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::BalanceSheet,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'سرمایه',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::BalanceSheet,
                                'system_key' => 'owner_capital',
                            ],
                            [
                                'code' => '02',
                                'name' => 'سود انباشته',
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
                'name' => 'درآمدها',
                'nature' => AccountNature::Credit,
                'statement_type' => StatementType::IncomeStatement,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'درآمدهای عملیاتی',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'فروش کالا',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'sales',
                            ],
                            [
                                'code' => '02',
                                'name' => 'درآمد خدمات',
                                'nature' => AccountNature::Credit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'services',
                            ],
                            [
                                'code' => '03',
                                'name' => 'تخفیفات فروش',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'sales_discount',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'سایر درآمدها',
                        'nature' => AccountNature::Credit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'سود تسعیر ارز',
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
                'name' => 'هزینه‌ها',
                'nature' => AccountNature::Debit,
                'statement_type' => StatementType::IncomeStatement,
                'children' => [
                    [
                        'code' => '1',
                        'name' => 'بهای تمام‌شده',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'بهای تمام‌شده کالای فروش‌رفته',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'cogs',
                            ],
                        ],
                    ],
                    [
                        'code' => '2',
                        'name' => 'هزینه‌های عملیاتی',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'هزینه اجاره',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'rent_expense',
                            ],
                            [
                                'code' => '02',
                                'name' => 'هزینه حقوق و دستمزد',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'payroll_expense',
                            ],
                            [
                                'code' => '03',
                                'name' => 'کارمزد بانکی',
                                'nature' => AccountNature::Debit,
                                'statement_type' => StatementType::IncomeStatement,
                                'system_key' => 'bank_fees',
                            ],
                        ],
                    ],
                    [
                        'code' => '3',
                        'name' => 'سایر هزینه‌ها',
                        'nature' => AccountNature::Debit,
                        'statement_type' => StatementType::IncomeStatement,
                        'children' => [
                            [
                                'code' => '01',
                                'name' => 'زیان تسعیر ارز',
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
