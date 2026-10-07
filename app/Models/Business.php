<?php

namespace App\Models;

use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Product;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'owner_id',
    'name',
    'slug',
    'type',
    'category',
    'phone',
    'address',
    'invoice_primary_color',
    'invoice_secondary_color',
    'is_active',
])]
class Business extends Model implements HasMedia
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const DEFAULT_INVOICE_PRIMARY_COLOR = '#0d9488';

    public const DEFAULT_INVOICE_SECONDARY_COLOR = '#134e4a';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BusinessType::class,
            'category' => BusinessCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']);
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function invoicePrimaryColor(): string
    {
        return filled($this->invoice_primary_color)
            ? $this->invoice_primary_color
            : self::DEFAULT_INVOICE_PRIMARY_COLOR;
    }

    public function invoiceSecondaryColor(): string
    {
        return filled($this->invoice_secondary_color)
            ? $this->invoice_secondary_color
            : self::DEFAULT_INVOICE_SECONDARY_COLOR;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<ShortLink, $this>
     */
    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot(['role'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<Party, $this>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function productCategories(): HasMany
    {
        return $this->categories()->ofType(CategoryType::Product);
    }

    /**
     * @return HasMany<Brand, $this>
     */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<BusinessCurrency, $this>
     */
    public function businessCurrencies(): HasMany
    {
        return $this->hasMany(BusinessCurrency::class);
    }

    /**
     * @return HasMany<Currency, $this>
     */
    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class);
    }

    public function baseBusinessCurrency(): ?BusinessCurrency
    {
        return $this->businessCurrencies()->where('is_base', true)->first();
    }

    public function activateCurrency(Currency $currency, string $exchangeRate = '1', bool $asBase = false): BusinessCurrency
    {
        return DB::transaction(function () use ($currency, $exchangeRate, $asBase): BusinessCurrency {
            $hasBase = $this->businessCurrencies()->where('is_base', true)->exists();
            $shouldBeBase = $asBase || ! $hasBase;

            $businessCurrency = BusinessCurrency::query()->firstOrCreate(
                [
                    'business_id' => $this->id,
                    'currency_id' => $currency->id,
                ],
                [
                    'is_base' => $shouldBeBase,
                    'exchange_rate_to_base' => $shouldBeBase ? 1 : $exchangeRate,
                ],
            );

            if ($shouldBeBase && ! $businessCurrency->is_base) {
                $businessCurrency->setAsBase();
            }

            BusinessCurrency::forgetCache($this->id);

            return $businessCurrency->fresh(['currency']);
        });
    }

    public function deactivateCurrency(Currency $currency): void
    {
        $businessCurrency = $this->businessCurrencies()
            ->where('currency_id', $currency->id)
            ->first();

        if ($businessCurrency === null) {
            return;
        }

        $hasAccounts = $this->accounts()->where('currency_id', $currency->id)->exists();

        if ($hasAccounts) {
            return;
        }

        $wasBase = $businessCurrency->is_base;
        $businessCurrency->delete();

        if ($wasBase) {
            $next = $this->businessCurrencies()->first();
            $next?->setAsBase();
        }

        BusinessCurrency::forgetCache($this->id);
    }
}
