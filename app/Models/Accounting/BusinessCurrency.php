<?php

namespace App\Models\Accounting;

use App\Models\Business;
use App\Models\Currency;
use Database\Factories\Accounting\BusinessCurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Fillable(['business_id', 'currency_id', 'is_base', 'exchange_rate_to_base'])]
class BusinessCurrency extends Model
{
    /** @use HasFactory<BusinessCurrencyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'exchange_rate_to_base' => 'decimal:18',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public static function cacheKey(int $businessId): string
    {
        return "business_currencies.{$businessId}";
    }

    public static function forgetCache(int $businessId): void
    {
        Cache::forget(self::cacheKey($businessId));
    }

    public function setAsBase(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('business_id', $this->business_id)
                ->whereKeyNot($this->id)
                ->update(['is_base' => false]);

            $this->update([
                'is_base' => true,
                'exchange_rate_to_base' => 1,
            ]);
        });

        self::forgetCache($this->business_id);
    }
}
