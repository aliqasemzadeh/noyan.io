<?php

namespace App\Actions\Business;

use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessRole;
use App\Enums\Business\BusinessType;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBusinessAction
{
    /**
     * @param  array{name: string, type: string|BusinessType, category: string|BusinessCategory, currency_id?: int|null}  $data
     */
    public function handle(User $user, array $data): Business
    {
        return DB::transaction(function () use ($user, $data): Business {
            $type = $data['type'] instanceof BusinessType
                ? $data['type']
                : BusinessType::from($data['type']);

            $category = $data['category'] instanceof BusinessCategory
                ? $data['category']
                : BusinessCategory::from($data['category']);

            $business = Business::query()->create([
                'owner_id' => $user->id,
                'name' => $data['name'],
                'slug' => $this->resolveUniqueSlug($data['name']),
                'type' => $type,
                'category' => $category,
                'is_active' => true,
            ]);

            BusinessUser::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'role' => BusinessRole::Owner,
            ]);

            $user->forceFill([
                'current_business_id' => $business->id,
            ])->save();

            $user->forgetBusinessesCache();

            $currency = $this->resolveCurrency($data['currency_id'] ?? null);
            $business->activateCurrency($currency, '1', true);

            $this->seedDefaultAccounts($business, $currency);

            return $business->fresh();
        });
    }

    protected function resolveUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'business';
        }

        $candidate = $base;
        $suffix = 1;

        while (Business::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function resolveCurrency(?int $currencyId): Currency
    {
        if ($currencyId !== null) {
            $currency = Currency::query()
                ->system()
                ->active()
                ->whereKey($currencyId)
                ->first();

            if ($currency !== null) {
                return $currency;
            }
        }

        $irt = Currency::query()
            ->system()
            ->active()
            ->where('code', 'IRT')
            ->first();

        if ($irt !== null) {
            return $irt;
        }

        return Currency::query()
            ->system()
            ->active()
            ->orderBy('code')
            ->firstOrFail();
    }

    protected function seedDefaultAccounts(Business $business, Currency $currency): void
    {
        $business->accounts()->createMany([
            [
                'currency_id' => $currency->id,
                'name' => __('general.default_cash_account'),
                'type' => AccountType::Asset,
                'sub_type' => AccountSubType::Cash,
                'opening_balance' => 0,
                'is_active' => true,
            ],
            [
                'currency_id' => $currency->id,
                'name' => __('general.default_bank_account'),
                'type' => AccountType::Asset,
                'sub_type' => AccountSubType::Bank,
                'opening_balance' => 0,
                'is_active' => true,
            ],
        ]);
    }
}
