<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\StockCountStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsBusinessActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id',
    'warehouse_id',
    'number',
    'status',
    'note',
    'counted_at',
    'created_by',
])]
class StockCount extends Model
{
    use BelongsToBusiness, LogsBusinessActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'counted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }
}
