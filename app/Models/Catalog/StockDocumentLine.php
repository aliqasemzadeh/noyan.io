<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_document_id',
    'product_id',
    'quantity',
    'received_quantity',
    'unit_cost',
    'sort_order',
])]
class StockDocumentLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:18',
            'received_quantity' => 'decimal:18',
            'unit_cost' => 'decimal:18',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'stock_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
