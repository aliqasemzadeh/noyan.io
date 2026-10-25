<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\TransactionType;
use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Models\Business;
use App\Models\Concerns\HasFloatingDetail;
use App\Models\Concerns\LogsBusinessActivity;
use App\Models\Currency;
use App\Support\AccountValidationRules;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'business_id',
    'currency_id',
    'name',
    'type',
    'sub_type',
    'bank_name',
    'account_number',
    'card_number',
    'iban',
    'note',
    'opening_balance',
    'current_balance',
    'is_active',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasFloatingDetail, LogsBusinessActivity, SoftDeletes;

    protected $table = 'accounting_accounts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'sub_type' => AccountSubType::class,
            'opening_balance' => 'decimal:18',
            'current_balance' => 'decimal:18',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AccountFactory
    {
        return AccountFactory::new();
    }

    protected static function booted(): void
    {
        $forgetCaches = function (Account $account): void {
            self::forgetOptionsCache((int) $account->business_id);
            self::forgetBalanceCache((int) $account->business_id, (int) $account->id);
        };

        static::deleted($forgetCaches);
        static::restored($forgetCaches);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected function floatingDetailType(): DetailType
    {
        return DetailType::Account;
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function primaryIdentifier(): ?string
    {
        return $this->iban
            ?: $this->account_number
            ?: $this->card_number;
    }

    /**
     * Recompute current balance from opening balance + ledger transactions.
     */
    public function balanceFromTransactions(): string
    {
        $accountId = (int) $this->id;

        $totals = Transaction::query()
            ->where(function ($query) use ($accountId): void {
                $query->where('account_id', $accountId)
                    ->orWhere('destination_account_id', $accountId);
            })
            ->toBase()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? AND account_id = ? THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = ? AND account_id = ? THEN amount ELSE 0 END), 0) as expense_total,
                COALESCE(SUM(CASE WHEN type = ? AND account_id = ? THEN amount ELSE 0 END), 0) as transfer_out_total,
                COALESCE(SUM(CASE WHEN type = ? AND destination_account_id = ? THEN amount ELSE 0 END), 0) as transfer_in_total',
                [
                    TransactionType::Income->value,
                    $accountId,
                    TransactionType::Expense->value,
                    $accountId,
                    TransactionType::Transfer->value,
                    $accountId,
                    TransactionType::Transfer->value,
                    $accountId,
                ]
            )
            ->first();

        $opening = (string) $this->opening_balance;
        $income = (string) ($totals->income_total ?? '0');
        $expense = (string) ($totals->expense_total ?? '0');
        $transferOut = (string) ($totals->transfer_out_total ?? '0');
        $transferIn = (string) ($totals->transfer_in_total ?? '0');

        $balance = bcadd($opening, $income, 18);
        $balance = bcsub($balance, $expense, 18);
        $balance = bcsub($balance, $transferOut, 18);
        $balance = bcadd($balance, $transferIn, 18);

        return AccountValidationRules::normalizeBalance($balance);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function createForBusiness(int $businessId, array $validated): self
    {
        $validated['business_id'] = $businessId;
        $validated['type'] = AccountType::Asset;
        $validated['opening_balance'] = (string) $validated['opening_balance'];
        $validated['current_balance'] = $validated['opening_balance'];
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $account = static::query()->create($validated);

        self::forgetOptionsCache($businessId);

        return $account;
    }

    public static function balanceCacheKey(int $businessId, int $accountId): string
    {
        return "account_balance.{$businessId}.{$accountId}";
    }

    public static function optionsCacheKey(int $businessId): string
    {
        return "business.{$businessId}.accounts.options";
    }

    public static function forgetBalanceCache(int $businessId, int $accountId): void
    {
        Cache::forget(self::balanceCacheKey($businessId, $accountId));
    }

    public static function forgetOptionsCache(int $businessId): void
    {
        Cache::forget(self::optionsCacheKey($businessId));
    }

    /**
     * @return Collection<int, Account>
     */
    public static function cachedOptionsForBusiness(int $businessId): Collection
    {
        $cacheKey = self::optionsCacheKey($businessId);

        $ids = Cache::remember($cacheKey, now()->addHour(), function () use ($businessId): array {
            return static::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('id')
                ->all();
        });

        if (! is_array($ids)) {
            self::forgetOptionsCache($businessId);

            $ids = static::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('id')
                ->all();

            Cache::put($cacheKey, $ids, now()->addHour());
        }

        if ($ids === []) {
            return new Collection;
        }

        return static::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);
    }
}
