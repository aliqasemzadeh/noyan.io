<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'IRR',
                'name' => 'Iranian Rial',
                'symbol' => '﷼',
                'decimal_places' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'BTC',
                'name' => 'Bitcoin',
                'symbol' => '₿',
                'decimal_places' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency,
            );
        }

        Currency::forgetActiveCache();
    }
}
