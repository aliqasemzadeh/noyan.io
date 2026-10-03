<?php

namespace App\Models\Accounting;

use App\Enums\PartyType;
use App\Models\Business;
use App\Models\User;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'business_id',
    'user_id',
    'type',
    'name',
    'legal_name',
    'economic_code',
    'national_id',
    'phone',
    'mobile',
    'email',
    'address',
    'postal_code',
    'balance',
    'credit_limit',
    'is_customer',
    'is_supplier',
    'is_active',
])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
            'balance' => 'decimal:18',
            'credit_limit' => 'decimal:18',
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): PartyFactory
    {
        return PartyFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (Party $party): void {
            if (! $party->isForceDeleting()) {
                $party->contacts()->each(function (PartyContact $contact): void {
                    $contact->delete();
                });
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PartyContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function displayName(): string
    {
        return $this->legal_name ?: $this->name;
    }

    public static function balanceCacheKey(int $businessId, int $partyId): string
    {
        return "party_balance.{$businessId}.{$partyId}";
    }

    public static function optionsCacheKey(int $businessId): string
    {
        return "business.{$businessId}.parties.options";
    }

    public static function forgetBalanceCache(int $businessId, int $partyId): void
    {
        Cache::forget(self::balanceCacheKey($businessId, $partyId));
    }

    public static function forgetOptionsCache(int $businessId): void
    {
        Cache::forget(self::optionsCacheKey($businessId));
    }
}
