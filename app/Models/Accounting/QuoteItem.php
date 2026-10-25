<?php

namespace App\Models\Accounting;

use App\Models\Catalog\Product;
use App\Models\Concerns\LogsBusinessActivity;
use App\Support\RichText;
use Database\Factories\Accounting\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'quote_id',
    'product_id',
    'title',
    'sku',
    'unit',
    'sort_order',
    'quantity',
    'unit_price',
    'discount_amount',
    'tax_amount',
    'total',
    'meta',
])]
class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory, LogsBusinessActivity, SoftDeletes;

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

    protected static function newFactory(): QuoteItemFactory
    {
        return QuoteItemFactory::new();
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function titleHtml(): string
    {
        return RichText::sanitize($this->title);
    }

    public function titlePlain(): string
    {
        return RichText::toPlainText($this->title);
    }
}
