<?php

namespace App\Models;

use App\Enums\CurrencyType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Support\Money;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable(['code', 'name', 'symbol', 'type', 'is_system', 'business_id', 'decimal_places', 'is_active'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, SoftDeletes;

    public const ACTIVE_CACHE_KEY = 'currencies.active';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CurrencyType::class,
            'is_system' => 'boolean',
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true)->whereNull('business_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAvailableToBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where(function (Builder $query) use ($businessId): void {
            $query->where(function (Builder $query): void {
                $query->system()->active();
            })->orWhere(function (Builder $query) use ($businessId): void {
                $query->where('business_id', $businessId)->where('is_active', true);
            });
        });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeEnabledForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->whereHas('businessCurrencies', function (Builder $query) use ($businessId): void {
            $query->where('business_id', $businessId);
        });
    }

    /**
     * @return Collection<int, Currency>
     */
    public static function cachedActive(): Collection
    {
        $ids = Cache::remember(self::ACTIVE_CACHE_KEY, now()->addHour(), function () {
            return static::query()
                ->system()
                ->active()
                ->orderBy('code')
                ->pluck('id')
                ->all();
        });

        if (! is_array($ids)) {
            self::forgetActiveCache();

            $ids = static::query()
                ->system()
                ->active()
                ->orderBy('code')
                ->pluck('id')
                ->all();

            Cache::put(self::ACTIVE_CACHE_KEY, $ids, now()->addHour());
        }

        if ($ids === []) {
            return new Collection;
        }

        return static::query()
            ->whereIn('id', $ids)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Currency>
     */
    public static function cachedForBusiness(int $businessId): Collection
    {
        $cacheKey = BusinessCurrency::cacheKey($businessId);

        $ids = Cache::remember($cacheKey, now()->addHour(), function () use ($businessId) {
            return static::query()
                ->enabledForBusiness($businessId)
                ->orderBy('code')
                ->pluck('id')
                ->all();
        });

        if (! is_array($ids)) {
            BusinessCurrency::forgetCache($businessId);

            $ids = static::query()
                ->enabledForBusiness($businessId)
                ->orderBy('code')
                ->pluck('id')
                ->all();

            Cache::put($cacheKey, $ids, now()->addHour());
        }

        if ($ids === []) {
            return new Collection;
        }

        return static::query()
            ->whereIn('id', $ids)
            ->with(['businessCurrencies' => fn ($query) => $query->where('business_id', $businessId)])
            ->orderBy('code')
            ->get();
    }

    public static function forgetActiveCache(): void
    {
        Cache::forget(self::ACTIVE_CACHE_KEY);
    }

    public function isInUse(): bool
    {
        return $this->accounts()->exists() || $this->businessCurrencies()->exists();
    }

    public function formatAmount(string|int $amount): string
    {
        return Money::format((string) $amount, $this->decimal_places);
    }
}
