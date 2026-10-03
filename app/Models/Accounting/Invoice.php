<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\InvoicePaymentStatus;
use App\Enums\Accounting\InvoiceType;
use App\Models\Business;
use App\Models\User;
use Database\Factories\Accounting\InvoiceFactory;
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
    'invoice_number',
    'type',
    'issue_date',
    'due_date',
    'finalized_at',
    'items_total',
    'global_discount',
    'global_tax',
    'total_amount',
    'paid_amount',
    'meta',
    'payment_status',
    'note',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'payment_status' => InvoicePaymentStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'finalized_at' => 'datetime',
            'items_total' => 'decimal:18',
            'global_discount' => 'decimal:18',
            'global_tax' => 'decimal:18',
            'total_amount' => 'decimal:18',
            'paid_amount' => 'decimal:18',
            'meta' => 'array',
        ];
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (Invoice $invoice): void {
            if ($invoice->isForceDeleting()) {
                return;
            }

            $originalNumber = $invoice->invoice_number;

            if (! str_contains($originalNumber, '__del_')) {
                $invoice->forceFill([
                    'invoice_number' => $originalNumber.'__del_'.$invoice->id,
                ])->saveQuietly();
            }

            $invoice->items()->each(function (InvoiceItem $item): void {
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

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function isDraft(): bool
    {
        return $this->finalized_at === null;
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function displayPartyName(): string
    {
        if ($this->party !== null) {
            return $this->party->displayName();
        }

        return (string) ($this->party_name ?? '');
    }
}
