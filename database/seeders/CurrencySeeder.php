<?php

namespace Database\Seeders;

use App\Enums\CurrencyType;
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
                'type' => CurrencyType::Fiat,
                'decimal_places' => 0,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'IRT',
                'name' => 'Iranian Toman',
                'symbol' => 'ت',
                'type' => CurrencyType::Fiat,
                'decimal_places' => 0,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'type' => CurrencyType::Fiat,
                'decimal_places' => 2,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'type' => CurrencyType::Fiat,
                'decimal_places' => 2,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'USDT',
                'name' => 'Tether',
                'symbol' => '₮',
                'type' => CurrencyType::Crypto,
                'decimal_places' => 2,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'BTC',
                'name' => 'Bitcoin',
                'symbol' => '₿',
                'type' => CurrencyType::Crypto,
                'decimal_places' => 8,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'XAU_GRAM',
                'name' => 'Gold (gram)',
                'symbol' => 'گرم',
                'type' => CurrencyType::Commodity,
                'decimal_places' => 3,
                'is_system' => true,
                'business_id' => null,
                'is_active' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code'], 'business_id' => null],
                $currency,
            );
        }

        Currency::forgetActiveCache();
    }
}
