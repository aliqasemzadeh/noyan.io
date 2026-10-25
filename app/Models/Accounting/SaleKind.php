<?php

namespace App\Models\Accounting;

use App\Models\Business;
use Database\Factories\Accounting\SaleKindFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\LogsBusinessActivity;

#[Fillable([
    'code',
    'name',
    'is_system',
    'business_id',
    'is_active',
    'sort_order',
])]
class SaleKind extends Model
{
    /** @use HasFactory<SaleKindFactory> */
    use HasFactory, LogsBusinessActivity, SoftDeletes;

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

    protected static function newFactory(): SaleKindFactory
    {
        return SaleKindFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<BusinessSaleKind, $this>
     */
    public function businessSaleKinds(): HasMany
    {
        return $this->hasMany(BusinessSaleKind::class);
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
            $sub->select('sale_kind_id')
                ->from('business_sale_kinds')
                ->where('business_id', $businessId);
        })->active()->orderBy('sort_order')->orderBy('name');
    }
}
