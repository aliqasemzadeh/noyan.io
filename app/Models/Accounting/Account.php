<?php

namespace App\Models\Accounting;

use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Models\Business;
use App\Models\Currency;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use HasFactory;

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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function primaryIdentifier(): ?string
    {
        return $this->iban
            ?: $this->account_number
            ?: $this->card_number;
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
}
