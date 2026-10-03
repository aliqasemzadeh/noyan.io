<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\LoanStatus;
use App\Enums\Accounting\LoanType;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\Accounting\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'party_id',
    'account_id',
    'type',
    'title',
    'principal_amount',
    'interest_amount',
    'total_amount',
    'paid_amount',
    'installments_count',
    'installment_amount',
    'issue_date',
    'first_installment_date',
    'status',
    'description',
])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use BelongsToBusiness, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LoanType::class,
            'status' => LoanStatus::class,
            'principal_amount' => 'decimal:18',
            'interest_amount' => 'decimal:18',
            'total_amount' => 'decimal:18',
            'paid_amount' => 'decimal:18',
            'installment_amount' => 'decimal:18',
            'issue_date' => 'date',
            'first_installment_date' => 'date',
        ];
    }

    protected static function newFactory(): LoanFactory
    {
        return LoanFactory::new();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function remainingAmount(): string
    {
        $remaining = bcsub((string) $this->total_amount, (string) $this->paid_amount, 18);

        return bccomp($remaining, '0', 18) === -1 ? '0' : $remaining;
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->remainingAmount(), '0', 18) !== 1;
    }
}
