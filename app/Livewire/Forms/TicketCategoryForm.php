<?php

namespace App\Livewire\Forms;

use App\Models\TicketCategory;
use Livewire\Form;

class TicketCategoryForm extends Form
{
    public ?TicketCategory $category = null;

    public string $title = '';

    public string $icon = '';

    public string $description = '';

    public int $sort_order = 1;

    public bool $is_active = true;

    public function setModel(TicketCategory $category): void
    {
        $this->category = $category;
        $this->title = $category->title;
        $this->icon = $category->icon ?? '';
        $this->description = $category->description ?? '';
        $this->sort_order = $category->sort_order;
        $this->is_active = $category->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'title' => __('general.title'),
            'icon' => __('general.icon'),
            'description' => __('general.description'),
            'sort_order' => __('general.sort_order'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): TicketCategory
    {
        $validated = $this->validate();
        $validated['icon'] = $validated['icon'] !== '' ? $validated['icon'] : null;
        $validated['description'] = $validated['description'] !== '' ? $validated['description'] : null;

        $category = TicketCategory::create($validated);

        $this->reset();

        return $category;
    }

    public function update(): void
    {
        $validated = $this->validate();
        $validated['icon'] = $validated['icon'] !== '' ? $validated['icon'] : null;
        $validated['description'] = $validated['description'] !== '' ? $validated['description'] : null;

        $this->category->update($validated);

        $this->reset();
    }
}
