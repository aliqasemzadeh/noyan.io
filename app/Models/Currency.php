<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active'])]
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
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<\App\Models\Accounting\Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(\App\Models\Accounting\Account::class);
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
     * @return \Illuminate\Database\Eloquent\Collection<int, Currency>
     */
    public static function cachedActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::ACTIVE_CACHE_KEY, now()->addHour(), function () {
            return static::query()
                ->active()
                ->orderBy('code')
                ->get();
        });
    }

    public static function forgetActiveCache(): void
    {
        Cache::forget(self::ACTIVE_CACHE_KEY);
    }

    public function formatAmount(string|int $amount): string
    {
        if (! function_exists('bcadd')) {
            return (string) $amount;
        }

        return bcadd((string) $amount, '0', $this->decimal_places);
    }
}
