<?php

namespace Database\Seeders;

use App\Models\Accounting\SettlementType;
use Illuminate\Database\Seeder;

class SettlementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'cash', 'name' => 'نقد', 'sort_order' => 10],
            ['code' => 'installment', 'name' => 'قسطی', 'sort_order' => 20],
            ['code' => 'temporary_credit', 'name' => 'اعتباری موقت', 'sort_order' => 30],
            ['code' => 'customary', 'name' => 'عرف', 'sort_order' => 40],
        ];

        foreach ($types as $type) {
            SettlementType::query()->updateOrCreate(
                [
                    'code' => $type['code'],
                    'business_id' => null,
                    'is_system' => true,
                ],
                [
                    'name' => $type['name'],
                    'is_active' => true,
                    'sort_order' => $type['sort_order'],
                    'deleted_at' => null,
                ],
            );
        }
    }
}
