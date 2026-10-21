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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'created_by',
    'party_id',
    'party_name',
    'buyer_legal_name',
    'buyer_national_id',
    'buyer_economic_code',
    'buyer_postal_code',
    'buyer_phone',
    'buyer_address',
    'quote_id',
    'invoice_number',
    'type',
    'sale_kind_id',
    'settlement_type',
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
    'moodian_tax_id',
    'moodian_status',
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

            if ($invoice->quote_id !== null) {
                Quote::query()
                    ->whereKey($invoice->quote_id)
                    ->where('invoice_id', $invoice->id)
                    ->update([
                        'invoice_id' => null,
                        'converted_at' => null,
                    ]);
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function saleKind(): BelongsTo
    {
        return $this->belongsTo(SaleKind::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Account, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'invoice_account')
            ->withTimestamps();
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
        if (filled($this->buyer_legal_name)) {
            return (string) $this->buyer_legal_name;
        }

        if ($this->party !== null) {
            return $this->party->displayName();
        }

        return (string) ($this->party_name ?? '');
    }

    public function buyerNationalId(): ?string
    {
        return filled($this->buyer_national_id)
            ? (string) $this->buyer_national_id
            : ($this->party?->national_id);
    }

    public function buyerEconomicCode(): ?string
    {
        return filled($this->buyer_economic_code)
            ? (string) $this->buyer_economic_code
            : ($this->party?->economic_code);
    }

    public function buyerPostalCode(): ?string
    {
        return filled($this->buyer_postal_code)
            ? (string) $this->buyer_postal_code
            : ($this->party?->postal_code);
    }

    public function buyerPhone(): ?string
    {
        if (filled($this->buyer_phone)) {
            return (string) $this->buyer_phone;
        }

        return $this->party?->mobile ?: $this->party?->phone;
    }

    public function buyerAddress(): ?string
    {
        return filled($this->buyer_address)
            ? (string) $this->buyer_address
            : ($this->party?->address);
    }
}
