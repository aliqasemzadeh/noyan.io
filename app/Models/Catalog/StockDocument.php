<?php

namespace App\Models\Catalog;

use App\Enums\Catalog\StockDocumentPriority;
use App\Enums\Catalog\StockDocumentStatus;
use App\Enums\Catalog\StockDocumentType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsBusinessActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id',
    'number',
    'type',
    'status',
    'priority',
    'warehouse_id',
    'destination_warehouse_id',
    'party_id',
    'invoice_id',
    'counterparty_name',
    'expected_at',
    'completed_at',
    'note',
    'created_by',
])]
class StockDocument extends Model
{
    use BelongsToBusiness, LogsBusinessActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockDocumentType::class,
            'status' => StockDocumentStatus::class,
            'priority' => StockDocumentPriority::class,
            'expected_at' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockDocumentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class)->orderBy('sort_order');
    }

    public function counterpartyLabel(): string
    {
        if ($this->party !== null) {
            return $this->party->displayName();
        }

        return (string) ($this->counterparty_name ?? '');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeTouchingWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where(function (Builder $query) use ($warehouseId): void {
            $query->where('warehouse_id', $warehouseId)
                ->orWhere('destination_warehouse_id', $warehouseId);
        });
    }
}
