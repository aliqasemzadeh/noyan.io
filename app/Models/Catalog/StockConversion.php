<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\StockConversionStatus;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Project;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsBusinessActivity;
use App\Models\User;
use Database\Factories\Catalog\StockConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'warehouse_id',
    'project_id',
    'cost_center_id',
    'conversion_number',
    'status',
    'note',
    'occurred_at',
    'posted_at',
    'created_by',
])]
class StockConversion extends Model
{
    /** @use HasFactory<StockConversionFactory> */
    use BelongsToBusiness, HasFactory, LogsBusinessActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockConversionStatus::class,
            'occurred_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StockConversionFactory
    {
        return StockConversionFactory::new();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockConversionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockConversionItem::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', StockConversionStatus::Posted);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', StockConversionStatus::Draft);
    }

    public function isPosted(): bool
    {
        return $this->status === StockConversionStatus::Posted;
    }
}
