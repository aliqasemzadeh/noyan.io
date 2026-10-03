<?php

namespace App\Livewire\Forms\Catalog;

use App\Models\Catalog\Brand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class BrandForm extends Form
{
    public ?Brand $brand = null;

    public string $name = '';

    public string $slug = '';

    public string $logo = '';

    public string $description = '';

    public bool $is_active = true;

    public function setModel(Brand $brand): void
    {
        $this->brand = $brand;
        $this->name = $brand->name;
        $this->slug = $brand->slug;
        $this->logo = $brand->logo ?? '';
        $this->description = $brand->description ?? '';
        $this->is_active = $brand->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('brands', 'slug')
                    ->where(fn ($query) => $query->where('business_id', $businessId))
                    ->ignore($this->brand?->id),
            ],
            'logo' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('general.name'),
            'slug' => __('general.slug'),
            'logo' => __('general.logo'),
            'description' => __('general.description'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): Brand
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['business_id'] = $businessId;
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name'], $businessId);
        $validated['logo'] = ($validated['logo'] ?? '') !== '' ? $validated['logo'] : null;
        $validated['description'] = ($validated['description'] ?? '') !== '' ? $validated['description'] : null;

        $brand = Brand::create($validated);
        $this->resetFormState();

        return $brand;
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();
        $validated['slug'] = $this->resolveSlug($validated['slug'] ?? null, $validated['name'], $businessId);
        $validated['logo'] = ($validated['logo'] ?? '') !== '' ? $validated['logo'] : null;
        $validated['description'] = ($validated['description'] ?? '') !== '' ? $validated['description'] : null;

        $this->brand->update($validated);
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
            $base = 'brand';
        }

        $candidate = $base;
        $suffix = 1;

        while (
            Brand::withTrashed()
                ->where('business_id', $businessId)
                ->where('slug', $candidate)
                ->when($this->brand, fn ($query) => $query->where('id', '!=', $this->brand->id))
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
        $this->is_active = true;
    }
}
