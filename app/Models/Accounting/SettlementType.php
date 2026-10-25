<?php

namespace App\Models\Accounting;

use App\Models\Business;
use Database\Factories\Accounting\SettlementTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'is_system',
    'business_id',
    'is_active',
    'sort_order',
])]
class SettlementType extends Model
{
    /** @use HasFactory<SettlementTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): SettlementTypeFactory
    {
        return SettlementTypeFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<BusinessSettlementType, $this>
     */
    public function businessSettlementTypes(): HasMany
    {
        return $this->hasMany(BusinessSettlementType::class);
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
        return $query->whereIn('id', function ($sub) use ($businessId): void {
            $sub->select('settlement_type_id')
                ->from('business_settlement_types')
                ->where('business_id', $businessId);
        })->active()->orderBy('sort_order')->orderBy('name');
    }
}
