<?php

namespace App\Livewire\Forms\Catalog;

use App\Enums\Catalog\PriceType;
use App\Enums\Catalog\ProductType;
use App\Enums\Catalog\ProductUnit;
use App\Enums\Catalog\StockMovementType;
use App\Enums\CategoryType;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductPriceHistory;
use App\Models\Catalog\ProductStockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ProductForm extends Form
{
    public ?Product $product = null;

    public ?int $category_id = null;

    public ?int $brand_id = null;

    public string $type = ProductType::Goods->value;

    public string $name = '';

    public string $slug = '';

    public string $sku = '';

    public string $barcode = '';

    public string $unit = ProductUnit::Piece->value;

    public string $description = '';

    public string $purchase_price = '0';

    public string $sale_price = '0';

    public bool $track_inventory = true;

    public string $stock_quantity = '0';

    public string $min_stock = '0';

    public string $max_stock = '';

    public string $tax_rate = '';

    public bool $is_active = true;

    public function setModel(Product $product): void
    {
        $this->product = $product;
        $this->category_id = $product->category_id;
        $this->brand_id = $product->brand_id;
        $this->type = $product->type->value;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode ?? '';
        $this->unit = $product->unit->value;
        $this->description = $product->description ?? '';
        $this->purchase_price = $this->formatAmount((string) $product->purchase_price);
        $this->sale_price = $this->formatAmount((string) $product->sale_price);
        $this->track_inventory = $product->track_inventory;
        $this->stock_quantity = $this->formatAmount((string) $product->stock_quantity);
        $this->min_stock = $this->formatAmount((string) $product->min_stock);
        $this->max_stock = $product->max_stock === null ? '' : $this->formatAmount((string) $product->max_stock);
        $this->tax_rate = $product->tax_rate === null ? '' : $this->formatAmount((string) $product->tax_rate);
        $this->is_active = $product->is_active;
    }

    public function updatedType(string $value): void
    {
        $type = ProductType::tryFrom($value);

        if ($type === null) {
            return;
        }

        $this->track_inventory = $type->tracksInventoryByDefault();

        if ($this->product !== null) {
            return;
        }

        $this->unit = match ($type) {
            ProductType::Service => ProductUnit::Hour->value,
            ProductType::Digital, ProductType::Goods => ProductUnit::Piece->value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $amount = ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/'];

        return [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('type', CategoryType::Product->value)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->where(fn ($query) => $query
                            ->whereNull('business_id')
                            ->orWhere('business_id', $businessId))),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')
                    ->where(fn ($query) => $query->where('business_id', $businessId))
                    ->ignore($this->product?->id),
            ],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('business_id', $businessId))
                    ->ignore($this->product?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'purchase_price' => $amount,
            'sale_price' => $amount,
            'track_inventory' => ['boolean'],
            'stock_quantity' => $amount,
            'min_stock' => $amount,
            'max_stock' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,18})?$/'],
            'tax_rate' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'category_id' => __('general.category'),
            'brand_id' => __('general.brand'),
            'type' => __('general.product_type'),
            'name' => __('general.name'),
            'slug' => __('general.slug'),
            'sku' => __('general.sku'),
            'barcode' => __('general.barcode'),
            'unit' => __('general.unit'),
            'description' => __('general.description'),
            'purchase_price' => __('general.purchase_price'),
            'sale_price' => __('general.sale_price'),
            'track_inventory' => __('general.track_inventory'),
            'stock_quantity' => __('general.stock_quantity'),
            'min_stock' => __('general.min_stock'),
            'max_stock' => __('general.max_stock'),
            'tax_rate' => __('general.tax_rate'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Product
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->prepareValidated($this->validate(), $businessId);

        return DB::transaction(function () use ($validated, $businessId): Product {
            $product = Product::create([
                ...$validated,
                'average_cost' => $validated['purchase_price'],
                'last_purchase_price' => $validated['purchase_price'],
                'reserved_quantity' => '0',
            ]);

            if ($product->track_inventory && bccomp((string) $product->stock_quantity, '0', 18) === 1) {
                ProductStockMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'movement_type' => StockMovementType::Opening,
                    'quantity' => $product->stock_quantity,
                    'unit_cost' => $product->purchase_price,
                    'balance_after' => $product->stock_quantity,
                    'note' => __('general.opening_stock_note'),
                    'occurred_at' => now(),
                    'created_by' => Auth::id(),
                ]);
            }

            $this->resetFormState();

            return $product;
        });
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->prepareValidated($this->validate(), $businessId);

        DB::transaction(function () use ($validated): void {
            $oldPurchase = (string) $this->product->purchase_price;
            $oldSale = (string) $this->product->sale_price;

            // Stock quantity is managed via stock movements after creation.
            unset($validated['stock_quantity']);

            $this->product->update($validated);

            $this->recordPriceChange(PriceType::Purchase, $oldPurchase, (string) $validated['purchase_price']);
            $this->recordPriceChange(PriceType::Sale, $oldSale, (string) $validated['sale_price']);
        });

        $this->resetFormState();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepareValidated(array $validated, int $businessId): array
    {
        $validated['business_id'] = $businessId;
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name'], $businessId);
        $validated['category_id'] = $validated['category_id'] ?: null;
        $validated['brand_id'] = $validated['brand_id'] ?: null;
        $validated['barcode'] = ($validated['barcode'] ?? '') !== '' ? $validated['barcode'] : null;
        $validated['description'] = ($validated['description'] ?? '') !== '' ? $validated['description'] : null;
        $validated['purchase_price'] = $this->normalizeAmount((string) $validated['purchase_price']);
        $validated['sale_price'] = $this->normalizeAmount((string) $validated['sale_price']);
        $validated['stock_quantity'] = $this->normalizeAmount((string) $validated['stock_quantity']);
        $validated['min_stock'] = $this->normalizeAmount((string) $validated['min_stock']);
        $validated['max_stock'] = ($validated['max_stock'] ?? '') !== ''
            ? $this->normalizeAmount((string) $validated['max_stock'])
            : null;
        $validated['tax_rate'] = ($validated['tax_rate'] ?? '') !== ''
            ? $this->normalizeAmount((string) $validated['tax_rate'])
            : null;

        $type = ProductType::tryFrom((string) $validated['type']);

        if ($type !== null && ! $type->tracksInventoryByDefault()) {
            $validated['track_inventory'] = false;
        }

        return $validated;
    }

    protected function recordPriceChange(PriceType $priceType, string $oldPrice, string $newPrice): void
    {
        if (bccomp($this->normalizeAmount($oldPrice), $this->normalizeAmount($newPrice), 18) === 0) {
            return;
        }

        ProductPriceHistory::create([
            'business_id' => $this->product->business_id,
            'product_id' => $this->product->id,
            'price_type' => $priceType,
            'old_price' => $this->normalizeAmount($oldPrice),
            'new_price' => $this->normalizeAmount($newPrice),
            'changed_by' => Auth::id(),
            'effective_at' => now(),
        ]);

        if ($priceType === PriceType::Purchase) {
            $this->product->forceFill([
                'last_purchase_price' => $this->normalizeAmount($newPrice),
            ])->save();
        }
    }

    protected function requireBusinessId(): int
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => __('general.business_required'),
            ]);
        }

        return (int) $businessId;
    }

    protected function resolveSlug(?string $slug, string $name, int $businessId): string
    {
        $base = filled($slug) ? Str::slug($slug) : Str::slug($name);

        if ($base === '') {
            $base = 'product';
        }

        $candidate = $base;
        $suffix = 1;

        while (
            Product::withTrashed()
                ->where('business_id', $businessId)
                ->where('slug', $candidate)
                ->when($this->product, fn ($query) => $query->where('id', '!=', $this->product->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function normalizeAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        return rtrim(rtrim($amount, '0'), '.') ?: '0';
    }

    protected function formatAmount(string $amount): string
    {
        return $this->normalizeAmount($amount);
    }

    protected function resetFormState(): void
    {
        $this->reset();
        $this->type = ProductType::Goods->value;
        $this->unit = ProductUnit::Piece->value;
        $this->purchase_price = '0';
        $this->sale_price = '0';
        $this->track_inventory = true;
        $this->stock_quantity = '0';
        $this->min_stock = '0';
        $this->is_active = true;
    }
}
