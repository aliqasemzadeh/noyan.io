<?php

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\Accounting\CostCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'business_id',
    'code',
    'name',
    'is_active',
])]
class CostCenter extends Model
{
    /** @use HasFactory<CostCenterFactory> */
    use BelongsToBusiness, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CostCenterFactory
    {
        return CostCenterFactory::new();
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function optionsCacheKey(int $businessId): string
    {
        return "business.{$businessId}.cost_centers.options";
    }

    public static function forgetOptionsCache(int $businessId): void
    {
        Cache::forget(self::optionsCacheKey($businessId));
    }

    /**
     * @return Collection<int, CostCenter>
     */
    public static function cachedOptionsForBusiness(int $businessId): Collection
    {
        $cacheKey = self::optionsCacheKey($businessId);

        $ids = Cache::remember($cacheKey, now()->addHour(), function () use ($businessId): array {
            return static::query()
                ->where('business_id', $businessId)
                ->active()
                ->orderBy('name')
                ->pluck('id')
                ->all();
        });

        if (! is_array($ids)) {
            self::forgetOptionsCache($businessId);

            $ids = static::query()
                ->where('business_id', $businessId)
                ->active()
                ->orderBy('name')
                ->pluck('id')
                ->all();

            Cache::put($cacheKey, $ids, now()->addHour());
        }

        if ($ids === []) {
            return new Collection;
        }

        return static::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
