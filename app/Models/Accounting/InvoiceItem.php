<?php

namespace App\Models\Accounting;

use App\Models\Catalog\Product;
use Database\Factories\Accounting\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'invoice_id',
    'product_id',
    'title',
    'sort_order',
    'quantity',
    'unit_price',
    'discount_amount',
    'tax_amount',
    'total',
    'meta',
])]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:18',
            'unit_price' => 'decimal:18',
            'discount_amount' => 'decimal:18',
            'tax_amount' => 'decimal:18',
            'total' => 'decimal:18',
            'meta' => 'array',
        ];
    }

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
