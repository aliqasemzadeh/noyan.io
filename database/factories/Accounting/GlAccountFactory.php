<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\AccountNature;
use App\Enums\Accounting\StatementType;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlAccount>
 */
class GlAccountFactory extends Factory
{
    protected $model = GlAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = (string) fake()->unique()->numberBetween(1000, 9999);

        return [
            'business_id' => Business::factory(),
            'parent_id' => null,
            'level' => AccountLevel::Group,
            'code' => $code,
            'full_code' => $code,
            'name' => fake()->words(2, true),
            'nature' => AccountNature::Debit,
            'statement_type' => StatementType::BalanceSheet,
            'is_postable' => false,
            'is_system' => false,
            'system_key' => null,
            'requires_detail_level_4_type' => null,
            'requires_detail_level_5_type' => null,
            'requires_detail_level_6_type' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function group(): static
    {
        return $this->state(fn (): array => [
            'level' => AccountLevel::Group,
            'is_postable' => false,
        ]);
    }

    public function control(): static
    {
        return $this->state(fn (): array => [
            'level' => AccountLevel::Control,
            'is_postable' => false,
        ]);
    }

    public function subsidiary(): static
    {
        return $this->state(fn (): array => [
            'level' => AccountLevel::Subsidiary,
            'is_postable' => true,
        ]);
    }
}
