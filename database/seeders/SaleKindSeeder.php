<?php

namespace Database\Seeders;

use App\Models\Accounting\SaleKind;
use Illuminate\Database\Seeder;

class SaleKindSeeder extends Seeder
{
    public function run(): void
    {
        $kinds = [
            ['code' => 'formal', 'name' => 'رسمی', 'sort_order' => 10],
            ['code' => 'informal', 'name' => 'غیررسمی', 'sort_order' => 20],
            ['code' => 'temporary_credit', 'name' => 'اعتباری موقت', 'sort_order' => 30],
            ['code' => 'cash', 'name' => 'نقد', 'sort_order' => 40],
            ['code' => 'installment', 'name' => 'قسطی', 'sort_order' => 50],
            ['code' => 'customary', 'name' => 'عرف', 'sort_order' => 60],
        ];

        foreach ($kinds as $kind) {
            SaleKind::query()->updateOrCreate(
                [
                    'code' => $kind['code'],
                    'business_id' => null,
                    'is_system' => true,
                ],
                [
                    'name' => $kind['name'],
                    'is_active' => true,
                    'sort_order' => $kind['sort_order'],
                ],
            );
        }
    }
}
