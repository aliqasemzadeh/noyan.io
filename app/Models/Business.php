<?php

namespace App\Models;

use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessType;
use App\Enums\Business\InvoiceOrientation;
use App\Enums\CategoryType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\BusinessSaleKind;
use App\Models\Accounting\BusinessSettlementType;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Loan;
use App\Models\Accounting\Party;
use App\Models\Accounting\Quote;
use App\Models\Accounting\SaleKind;
use App\Models\Accounting\SettlementType;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Product;
use App\Models\Catalog\StockConversion;
use App\Models\Catalog\Warehouse;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Concerns\LogsBusinessActivity;

#[Fillable([
    'owner_id',
    'name',
    'legal_name',
    'slug',
    'type',
    'category',
    'phone',
    'address',
    'national_id',
    'economic_code',
    'postal_code',
    'tax_memory_id',
    'invoice_primary_color',
    'invoice_secondary_color',
    'invoice_orientation',
    'is_active',
    'sms_wallet_balance',
])]
class Business extends Model implements HasMedia
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, InteractsWithMedia, LogsBusinessActivity, SoftDeletes;

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
            'invoice_orientation' => InvoiceOrientation::class,
            'is_active' => 'boolean',
            'sms_wallet_balance' => 'decimal:2',
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

    public function invoiceOrientation(): InvoiceOrientation
    {
        return $this->invoice_orientation ?? InvoiceOrientation::Portrait;
    }

    public function displayLegalName(): string
    {
        return filled($this->legal_name) ? (string) $this->legal_name : (string) $this->name;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<BusinessSaleKind, $this>
     */
    public function businessSaleKinds(): HasMany
    {
        return $this->hasMany(BusinessSaleKind::class);
    }

    public function activateSaleKind(SaleKind $saleKind, bool $asDefault = false): BusinessSaleKind
    {
        return DB::transaction(function () use ($saleKind, $asDefault): BusinessSaleKind {
            $hasDefault = $this->businessSaleKinds()->where('is_default', true)->exists();
            $shouldBeDefault = $asDefault || ! $hasDefault;

            $businessSaleKind = BusinessSaleKind::query()->firstOrCreate(
                [
                    'business_id' => $this->id,
                    'sale_kind_id' => $saleKind->id,
                ],
                [
                    'is_default' => $shouldBeDefault,
                ],
            );

            if ($shouldBeDefault && ! $businessSaleKind->is_default) {
                $businessSaleKind->setAsDefault();
            }

            return $businessSaleKind->fresh(['saleKind']);
        });
    }

    public function deactivateSaleKind(SaleKind $saleKind): void
    {
        $businessSaleKind = $this->businessSaleKinds()
            ->where('sale_kind_id', $saleKind->id)
            ->first();

        if ($businessSaleKind === null) {
            return;
        }

        $wasDefault = $businessSaleKind->is_default;
        $businessSaleKind->delete();

        if ($wasDefault) {
            $this->businessSaleKinds()->first()?->setAsDefault();
        }
    }

    public function defaultSaleKind(): ?SaleKind
    {
        $default = $this->businessSaleKinds()
            ->with('saleKind')
            ->where('is_default', true)
            ->first();

        if ($default?->saleKind !== null) {
            return $default->saleKind;
        }

        return $this->businessSaleKinds()
            ->with('saleKind')
            ->first()
            ?->saleKind;
    }

    /**
     * @return HasMany<BusinessSettlementType, $this>
     */
    public function businessSettlementTypes(): HasMany
    {
        return $this->hasMany(BusinessSettlementType::class);
    }

    public function activateSettlementType(SettlementType $settlementType, bool $asDefault = false): BusinessSettlementType
    {
        return DB::transaction(function () use ($settlementType, $asDefault): BusinessSettlementType {
            $hasDefault = $this->businessSettlementTypes()->where('is_default', true)->exists();
            $shouldBeDefault = $asDefault || ! $hasDefault;

            $businessSettlementType = BusinessSettlementType::query()->firstOrCreate(
                [
                    'business_id' => $this->id,
                    'settlement_type_id' => $settlementType->id,
                ],
                [
                    'is_default' => $shouldBeDefault,
                ],
            );

            if ($shouldBeDefault && ! $businessSettlementType->is_default) {
                $businessSettlementType->setAsDefault();
            }

            return $businessSettlementType->fresh(['settlementType']);
        });
    }

    public function deactivateSettlementType(SettlementType $settlementType): void
    {
        $businessSettlementType = $this->businessSettlementTypes()
            ->where('settlement_type_id', $settlementType->id)
            ->first();

        if ($businessSettlementType === null) {
            return;
        }

        $wasDefault = $businessSettlementType->is_default;
        $businessSettlementType->delete();

        if ($wasDefault) {
            $this->businessSettlementTypes()->first()?->setAsDefault();
        }
    }

    public function defaultSettlementType(): ?SettlementType
    {
        $default = $this->businessSettlementTypes()
            ->with('settlementType')
            ->where('is_default', true)
            ->first();

        if ($default?->settlementType !== null) {
            return $default->settlementType;
        }

        return $this->businessSettlementTypes()
            ->with('settlementType')
            ->first()
            ?->settlementType;
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

    /**
     * @return HasMany<BusinessInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(BusinessInvitation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot(['roles', 'permissions'])
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

    public function invoiceSettings(): HasOne
    {
        return $this->hasOne(BusinessInvoiceSetting::class);
    }

    /**
     * @return HasMany<BusinessSmsWalletTransaction, $this>
     */
    public function smsWalletTransactions(): HasMany
    {
        return $this->hasMany(BusinessSmsWalletTransaction::class);
    }

    public function resolveInvoiceSettings(): BusinessInvoiceSetting
    {
        return BusinessInvoiceSetting::forBusiness($this);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
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
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function defaultWarehouse(): ?Warehouse
    {
        return $this->warehouses()->default()->first();
    }

    /**
     * @return HasMany<StockConversion, $this>
     */
    public function stockConversions(): HasMany
    {
        return $this->hasMany(StockConversion::class);
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
