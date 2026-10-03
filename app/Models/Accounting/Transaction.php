<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\TransactionType;
use App\Models\Category;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Database\Factories\Accounting\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'business_id',
    'created_by',
    'account_id',
    'destination_account_id',
    'party_id',
    'invoice_id',
    'category_id',
    'type',
    'transaction_date',
    'currency',
    'exchange_rate',
    'amount',
    'base_amount',
    'reference_number',
    'note',
    'meta',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToBusiness, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'transaction_date' => 'date',
            'exchange_rate' => 'decimal:18',
            'amount' => 'decimal:18',
            'base_amount' => 'decimal:18',
            'meta' => 'array',
        ];
    }

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    protected static function booted(): void
    {
        $forgetBalances = function (Transaction $transaction): void {
            $businessId = (int) $transaction->business_id;

            Account::forgetBalanceCache($businessId, (int) $transaction->account_id);
            Account::forgetOptionsCache($businessId);

            if ($transaction->destination_account_id !== null) {
                Account::forgetBalanceCache($businessId, (int) $transaction->destination_account_id);
            }

            if ($transaction->party_id !== null) {
                Party::forgetBalanceCache($businessId, (int) $transaction->party_id);
                Party::forgetOptionsCache($businessId);
            }

            $originalAccountId = $transaction->getOriginal('account_id');
            if ($originalAccountId !== null && (int) $originalAccountId !== (int) $transaction->account_id) {
                Account::forgetBalanceCache($businessId, (int) $originalAccountId);
            }

            $originalDestinationId = $transaction->getOriginal('destination_account_id');
            if ($originalDestinationId !== null && (int) $originalDestinationId !== (int) $transaction->destination_account_id) {
                Account::forgetBalanceCache($businessId, (int) $originalDestinationId);
            }

            $originalPartyId = $transaction->getOriginal('party_id');
            if ($originalPartyId !== null && (int) $originalPartyId !== (int) $transaction->party_id) {
                Party::forgetBalanceCache($businessId, (int) $originalPartyId);
            }
        };

        static::created($forgetBalances);
        static::updated($forgetBalances);
        static::deleted($forgetBalances);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
