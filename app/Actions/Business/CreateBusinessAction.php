<?php

namespace App\Actions\Business;

use App\Actions\Accounting\SeedBusinessChartOfAccountsAction;
use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Inventory\InitializeBusinessWarehousesAction;
use App\Enums\Accounting\ChartTemplate;
use App\Enums\AccountSubType;
use App\Enums\AccountType;
use App\Enums\Business\BusinessCategory;
use App\Enums\Business\BusinessRole;
use App\Enums\Business\BusinessType;
use App\Enums\Business\InvoiceOrientation;
use App\Models\Business;
use App\Models\BusinessInvoiceSetting;
use App\Models\BusinessLedgerSetting;
use App\Models\BusinessUser;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateBusinessAction
{
    /**
     * @param  array{
     *     name: string,
     *     type: string|BusinessType,
     *     category: string|BusinessCategory,
     *     legal_name?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     national_id?: string|null,
     *     economic_code?: string|null,
     *     postal_code?: string|null,
     *     tax_memory_id?: string|null,
     *     invoice_primary_color?: string|null,
     *     invoice_secondary_color?: string|null,
     *     invoice_orientation?: string|InvoiceOrientation|null,
     *     logo?: TemporaryUploadedFile|UploadedFile|null,
     *     currency_ids?: list<int|string>,
     *     currency_id?: int|null
     * }  $data
     */
    public function handle(User $user, array $data): Business
    {
        return DB::transaction(function () use ($user, $data): Business {
            $type = $data['type'] instanceof BusinessType
                ? $data['type']
                : BusinessType::from($data['type']);

            $category = $data['category'] instanceof BusinessCategory
                ? $data['category']
                : BusinessCategory::from($data['category']);

            $orientation = isset($data['invoice_orientation'])
                ? ($data['invoice_orientation'] instanceof InvoiceOrientation
                    ? $data['invoice_orientation']
                    : InvoiceOrientation::from((string) $data['invoice_orientation']))
                : InvoiceOrientation::Portrait;

            $business = Business::query()->create([
                'owner_id' => $user->id,
                'name' => $data['name'],
                'legal_name' => filled($data['legal_name'] ?? null) ? $data['legal_name'] : null,
                'slug' => $this->resolveUniqueSlug($data['name']),
                'type' => $type,
                'category' => $category,
                'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
                'address' => filled($data['address'] ?? null) ? $data['address'] : null,
                'national_id' => filled($data['national_id'] ?? null) ? $data['national_id'] : null,
                'economic_code' => filled($data['economic_code'] ?? null) ? $data['economic_code'] : null,
                'postal_code' => filled($data['postal_code'] ?? null) ? $data['postal_code'] : null,
                'tax_memory_id' => filled($data['tax_memory_id'] ?? null) ? $data['tax_memory_id'] : null,
                'invoice_primary_color' => filled($data['invoice_primary_color'] ?? null)
                    ? $data['invoice_primary_color']
                    : null,
                'invoice_secondary_color' => filled($data['invoice_secondary_color'] ?? null)
                    ? $data['invoice_secondary_color']
                    : null,
                'invoice_orientation' => $orientation,
                'is_active' => true,
            ]);

            if (($data['logo'] ?? null) !== null) {
                $business
                    ->addMedia($data['logo'])
                    ->toMediaCollection('logo');
            }

            BusinessUser::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'roles' => [BusinessRole::Owner->value],
                'permissions' => [
                    BusinessRole::Owner->value => BusinessRole::Owner->defaultPermissions(),
                ],
            ]);

            $user->forceFill([
                'current_business_id' => $business->id,
            ])->save();

            $user->forgetBusinessesCache();

            $currencies = $this->resolveCurrencies($data);
            $baseCurrency = $currencies->first();

            foreach ($currencies as $index => $currency) {
                $business->activateCurrency($currency, '1', $index === 0);
            }

            $this->seedDefaultAccounts($business, $baseCurrency);

            app(InitializeBusinessWarehousesAction::class)->handle($business);

            BusinessInvoiceSetting::forBusiness($business);
            BusinessLedgerSetting::forBusiness($business);

            $template = $business->category?->chartTemplate() ?? ChartTemplate::Trading;
            app(SeedBusinessChartOfAccountsAction::class)->handle($business, template: $template);
            app(SyncBusinessLedgerDefaultAccountsAction::class)->handle($business, overwrite: true);

            return $business->fresh();
        });
    }

    protected function resolveUniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'business';
        }

        $candidate = $base;
        $suffix = 1;

        while (Business::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array{currency_ids?: list<int|string>, currency_id?: int|null}  $data
     * @return Collection<int, Currency>
     */
    protected function resolveCurrencies(array $data): Collection
    {
        $ids = collect($data['currency_ids'] ?? [])
            ->when(
                filled($data['currency_id'] ?? null),
                fn (Collection $collection): Collection => $collection->prepend($data['currency_id']),
            )
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $currencies = collect();

        if ($ids->isNotEmpty()) {
            $found = Currency::query()
                ->system()
                ->active()
                ->whereKey($ids->all())
                ->get()
                ->keyBy('id');

            $currencies = $ids
                ->map(fn (int $id): ?Currency => $found->get($id))
                ->filter()
                ->values();
        }

        if ($currencies->isNotEmpty()) {
            return $currencies;
        }

        $fallback = $this->resolveCurrency(null);

        return collect([$fallback]);
    }

    protected function resolveCurrency(?int $currencyId): Currency
    {
        if ($currencyId !== null) {
            $currency = Currency::query()
                ->system()
                ->active()
                ->whereKey($currencyId)
                ->first();

            if ($currency !== null) {
                return $currency;
            }
        }

        $irt = Currency::query()
            ->system()
            ->active()
            ->where('code', 'IRT')
            ->first();

        if ($irt !== null) {
            return $irt;
        }

        return Currency::query()
            ->system()
            ->active()
            ->orderBy('code')
            ->firstOrFail();
    }

    protected function seedDefaultAccounts(Business $business, Currency $currency): void
    {
        $business->accounts()->createMany([
            [
                'currency_id' => $currency->id,
                'name' => __('general.default_cash_account'),
                'type' => AccountType::Asset,
                'sub_type' => AccountSubType::Cash,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ],
            [
                'currency_id' => $currency->id,
                'name' => __('general.default_bank_account'),
                'type' => AccountType::Asset,
                'sub_type' => AccountSubType::Bank,
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ],
        ]);
    }
}
