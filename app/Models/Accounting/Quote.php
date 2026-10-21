<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\QuoteStatus;
use App\Models\Business;
use App\Models\User;
use Database\Factories\Accounting\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'created_by',
    'party_id',
    'party_name',
    'quote_number',
    'sale_kind_id',
    'issue_date',
    'valid_until',
    'finalized_at',
    'items_total',
    'global_discount',
    'global_tax',
    'total_amount',
    'meta',
    'note',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'finalized_at' => 'datetime',
            'converted_at' => 'datetime',
            'items_total' => 'decimal:18',
            'global_discount' => 'decimal:18',
            'global_tax' => 'decimal:18',
            'total_amount' => 'decimal:18',
            'meta' => 'array',
        ];
    }

    protected static function newFactory(): QuoteFactory
    {
        return QuoteFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (Quote $quote): void {
            if ($quote->isForceDeleting()) {
                return;
            }

            $originalNumber = $quote->quote_number;

            if (! str_contains($originalNumber, '__del_')) {
                $quote->forceFill([
                    'quote_number' => $originalNumber.'__del_'.$quote->id,
                ])->saveQuietly();
            }

            $quote->items()->each(function (QuoteItem $item): void {
                $item->delete();
            });
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function isDraft(): bool
    {
        return $this->finalized_at === null;
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null && $this->invoice_id !== null;
    }

    public function isExpired(): bool
    {
        if (! $this->isFinalized() || $this->isConverted()) {
            return false;
        }

        return $this->valid_until !== null
            && $this->valid_until->toDateString() < now()->toDateString();
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function isDeletable(): bool
    {
        return ! $this->isConverted();
    }

    public function canBeConverted(): bool
    {
        return $this->isFinalized() && ! $this->isConverted();
    }

    public function status(): QuoteStatus
    {
        if ($this->isConverted()) {
            return QuoteStatus::Converted;
        }

        if ($this->isExpired()) {
            return QuoteStatus::Expired;
        }

        if ($this->isFinalized()) {
            return QuoteStatus::Finalized;
        }

        return QuoteStatus::Draft;
    }

    public function displayPartyName(): string
    {
        if ($this->party !== null) {
            return $this->party->displayName();
        }

        return (string) ($this->party_name ?? '');
    }
}
