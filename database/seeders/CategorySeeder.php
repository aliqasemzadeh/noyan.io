<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $type => $tree) {
            $categoryType = CategoryType::from($type);
            $this->seedTree($categoryType, $tree, null);
            Category::forgetSystemCache($categoryType);
        }
    }

    /**
     * @return array<string, list<array{code: string, name: string, children?: list<mixed>}>>
     */
    protected function definitions(): array
    {
        return [
            CategoryType::Expense->value => [
                [
                    'code' => 'operating',
                    'name' => 'هزینه‌های عملیاتی',
                    'children' => [
                        ['code' => 'rent', 'name' => 'اجاره دفتر'],
                        ['code' => 'utilities', 'name' => 'آب و برق و گاز'],
                        [
                            'code' => 'payroll',
                            'name' => 'حقوق و دستمزد',
                            'children' => [
                                ['code' => 'salary', 'name' => 'حقوق پایه'],
                                ['code' => 'insurance', 'name' => 'بیمه'],
                                ['code' => 'payroll_tax', 'name' => 'مالیات حقوق'],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'admin',
                    'name' => 'هزینه‌های اداری',
                    'children' => [
                        ['code' => 'office_supplies', 'name' => 'ملزومات اداری'],
                        ['code' => 'maintenance', 'name' => 'تعمیر و نگهداری'],
                    ],
                ],
                [
                    'code' => 'financial_expense',
                    'name' => 'هزینه‌های مالی',
                    'children' => [
                        ['code' => 'bank_fee', 'name' => 'کارمزد بانکی'],
                        ['code' => 'tax', 'name' => 'مالیات و عوارض'],
                    ],
                ],
                ['code' => 'marketing', 'name' => 'تبلیغات و بازاریابی'],
                ['code' => 'transport', 'name' => 'حمل و نقل'],
            ],
            CategoryType::Income->value => [
                ['code' => 'sales', 'name' => 'فروش کالا'],
                ['code' => 'services', 'name' => 'ارائه خدمات'],
                [
                    'code' => 'financial_income',
                    'name' => 'درآمد مالی',
                    'children' => [
                        ['code' => 'interest', 'name' => 'سود سپرده'],
                        ['code' => 'fx_gain', 'name' => 'سود تسعیر ارز'],
                    ],
                ],
                ['code' => 'other_income', 'name' => 'سایر درآمدها'],
            ],
            CategoryType::Asset->value => [
                [
                    'code' => 'current_asset',
                    'name' => 'دارایی جاری',
                    'children' => [
                        ['code' => 'cash_bank', 'name' => 'موجودی نقد و بانک'],
                        ['code' => 'receivable', 'name' => 'حساب‌های دریافتنی'],
                        ['code' => 'inventory', 'name' => 'موجودی کالا'],
                    ],
                ],
                [
                    'code' => 'fixed_asset',
                    'name' => 'دارایی ثابت',
                    'children' => [
                        ['code' => 'property', 'name' => 'اموال و ماشین‌آلات'],
                        ['code' => 'equipment', 'name' => 'تجهیزات اداری'],
                    ],
                ],
            ],
            CategoryType::Liability->value => [
                [
                    'code' => 'current_liability',
                    'name' => 'بدهی جاری',
                    'children' => [
                        ['code' => 'payable', 'name' => 'حساب‌های پرداختنی'],
                        ['code' => 'short_term_loan', 'name' => 'وام کوتاه‌مدت'],
                        ['code' => 'tax_payable', 'name' => 'مالیات پرداختنی'],
                    ],
                ],
                [
                    'code' => 'long_term_liability',
                    'name' => 'بدهی بلندمدت',
                    'children' => [
                        ['code' => 'long_term_loan', 'name' => 'وام بلندمدت'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{code: string, name: string, children?: list<mixed>}>  $nodes
     */
    protected function seedTree(CategoryType $type, array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $index => $node) {
            $category = Category::query()->updateOrCreate(
                [
                    'type' => $type,
                    'code' => $node['code'],
                    'business_id' => null,
                ],
                [
                    'parent_id' => $parentId,
                    'name' => $node['name'],
                    'slug' => Str::slug($node['code']),
                    'is_system' => true,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );

            $this->seedTree($type, $node['children'] ?? [], $category->id);
        }
    }
}
