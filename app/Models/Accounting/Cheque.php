<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\ChequeStatus;
use App\Enums\Accounting\ChequeType;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Database\Factories\Accounting\ChequeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'business_id',
    'party_id',
    'invoice_id',
    'account_id',
    'created_by',
    'type',
    'status',
    'cheque_number',
    'sayad_number',
    'bank_name',
    'bank_branch',
    'amount',
    'issue_date',
    'due_date',
    'cleared_at',
    'status_changed_at',
    'party_reversed_at',
    'note',
])]
class Cheque extends Model
{
    /** @use HasFactory<ChequeFactory> */
    use BelongsToBusiness, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChequeType::class,
            'status' => ChequeStatus::class,
            'amount' => 'decimal:18',
            'issue_date' => 'date',
            'due_date' => 'date',
            'cleared_at' => 'date',
            'status_changed_at' => 'datetime',
            'party_reversed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ChequeFactory
    {
        return ChequeFactory::new();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isOverdue(): bool
    {
        if (! $this->status instanceof ChequeStatus || ! $this->status->isOpen()) {
            return false;
        }

        return $this->due_date !== null && $this->due_date->isBefore(now()->startOfDay());
    }

    public function daysUntilDue(): int
    {
        if ($this->due_date === null) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
    }

    /**
     * @return list<ChequeStatus>
     */
    public function allowedTransitions(): array
    {
        if (! $this->status instanceof ChequeStatus || ! $this->type instanceof ChequeType) {
            return [];
        }

        return $this->status->allowedTransitions($this->type);
    }

    /**
     * @param  Builder<Cheque>  $query
     * @return Builder<Cheque>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ChequeStatus::openValues());
    }

    /**
     * @param  Builder<Cheque>  $query
     * @return Builder<Cheque>
     */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query
            ->open()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * @param  Builder<Cheque>  $query
     * @return Builder<Cheque>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->open()
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public static function alertsCacheKey(int $businessId): string
    {
        return "business.{$businessId}.cheque_alerts";
    }

    public static function forgetAlertsCache(int $businessId): void
    {
        Cache::forget(self::alertsCacheKey($businessId));
    }
}
