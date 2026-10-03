<?php

namespace App\Livewire\Forms\Catalog;

use App\Models\Catalog\ProductCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ProductCategoryForm extends Form
{
    public ?ProductCategory $category = null;

    public ?int $parent_id = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function setModel(ProductCategory $category): void
    {
        $this->category = $category;
        $this->parent_id = $category->parent_id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = $category->description ?? '';
        $this->sort_order = (int) $category->sort_order;
        $this->is_active = $category->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->whereNull('deleted_at')
                        ->when($this->category, fn ($query) => $query->where('id', '!=', $this->category->id))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_categories', 'slug')
                    ->where(fn ($query) => $query->where('business_id', $businessId))
                    ->ignore($this->category?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parent_id' => __('general.parent_category'),
            'name' => __('general.name'),
            'slug' => __('general.slug'),
            'description' => __('general.description'),
            'sort_order' => __('general.sort_order'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): ProductCategory
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['business_id'] = $businessId;
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name'], $businessId);
        $validated['description'] = ($validated['description'] ?? '') !== '' ? $validated['description'] : null;
        $validated['parent_id'] = $validated['parent_id'] ?: null;

        $category = ProductCategory::create($validated);
        $this->resetFormState();

        return $category;
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name'], $businessId);
        $validated['description'] = ($validated['description'] ?? '') !== '' ? $validated['description'] : null;
        $validated['parent_id'] = $validated['parent_id'] ?: null;

        $this->category->update($validated);
        $this->resetFormState();
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
            $base = 'category';
        }

        $candidate = $base;
        $suffix = 1;

        while (
            ProductCategory::withTrashed()
                ->where('business_id', $businessId)
                ->where('slug', $candidate)
                ->when($this->category, fn ($query) => $query->where('id', '!=', $this->category->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function resetFormState(): void
    {
        $this->reset();
        $this->sort_order = 0;
        $this->is_active = true;
    }
}
