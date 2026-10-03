<?php

namespace App\Livewire\Forms;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class CustomCategoryForm extends Form
{
    public ?Category $category = null;

    public ?int $parent_id = null;

    public string $type = CategoryType::Product->value;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function setModel(Category $category): void
    {
        $this->category = $category;
        $this->parent_id = $category->parent_id;
        $this->type = $category->type->value;
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
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('type', $this->type)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->where(fn ($query) => $query
                            ->whereNull('business_id')
                            ->orWhere('business_id', $businessId))
                        ->when($this->category, fn ($query) => $query->where('id', '!=', $this->category->id))),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $this->category === null) {
                        return;
                    }

                    if (in_array((int) $value, $this->category->descendantIds(), true)) {
                        $fail(__('general.category_parent_cannot_be_descendant'));
                    }
                },
            ],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('type', $this->type)
                        ->whereNull('deleted_at'))
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
            'type' => __('general.category_type'),
            'name' => __('general.name'),
            'slug' => __('general.slug'),
            'description' => __('general.description'),
            'sort_order' => __('general.sort_order'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Category
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['business_id'] = $businessId;
        $validated['is_system'] = false;
        $validated['code'] = null;
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name'], $businessId);
        $validated['description'] = ($validated['description'] ?? '') !== '' ? $validated['description'] : null;
        $validated['parent_id'] = $validated['parent_id'] ?: null;

        $category = Category::create($validated);
        $this->resetFormState();

        return $category;
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();

        if ($this->category === null || ! $this->category->isEditableByBusiness($businessId)) {
            throw ValidationException::withMessages([
                'name' => __('general.category_not_editable'),
            ]);
        }

        $validated = $this->validate();
        unset($validated['type']);
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
            Category::withTrashed()
                ->where('business_id', $businessId)
                ->where('type', $this->type)
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
        $type = $this->type;
        $this->reset();
        $this->type = $type !== '' ? $type : CategoryType::Product->value;
        $this->sort_order = 0;
        $this->is_active = true;
    }
}
