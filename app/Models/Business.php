<?php

namespace App\Models;

use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable(['owner_id', 'name', 'slug', 'type', 'category', 'is_active'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BusinessType::class,
            'category' => BusinessCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot(['role'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<BusinessCurrency, $this>
     */
    public function businessCurrencies(): HasMany
    {
        return $this->hasMany(BusinessCurrency::class);
    }

    /**
     * @return HasMany<Currency, $this>
     */
    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class);
    }

    public function baseBusinessCurrency(): ?BusinessCurrency
    {
        return $this->businessCurrencies()->where('is_base', true)->first();
    }

    public function activateCurrency(Currency $currency, string $exchangeRate = '1', bool $asBase = false): BusinessCurrency
    {
        return DB::transaction(function () use ($currency, $exchangeRate, $asBase): BusinessCurrency {
            $hasBase = $this->businessCurrencies()->where('is_base', true)->exists();
            $shouldBeBase = $asBase || ! $hasBase;

            $businessCurrency = BusinessCurrency::query()->firstOrCreate(
                [
                    'business_id' => $this->id,
                    'currency_id' => $currency->id,
                ],
                [
                    'is_base' => $shouldBeBase,
                    'exchange_rate_to_base' => $shouldBeBase ? 1 : $exchangeRate,
                ],
            );

            if ($shouldBeBase && ! $businessCurrency->is_base) {
                $businessCurrency->setAsBase();
            }

            BusinessCurrency::forgetCache($this->id);

            return $businessCurrency->fresh(['currency']);
        });
    }

    public function deactivateCurrency(Currency $currency): void
    {
        $businessCurrency = $this->businessCurrencies()
            ->where('currency_id', $currency->id)
            ->first();

        if ($businessCurrency === null) {
            return;
        }

        $hasAccounts = $this->accounts()->where('currency_id', $currency->id)->exists();

        if ($hasAccounts) {
            return;
        }

        $wasBase = $businessCurrency->is_base;
        $businessCurrency->delete();

        if ($wasBase) {
            $next = $this->businessCurrencies()->first();
            $next?->setAsBase();
        }

        BusinessCurrency::forgetCache($this->id);
    }
}
