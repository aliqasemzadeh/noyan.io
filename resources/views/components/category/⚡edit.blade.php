<?php

use App\Livewire\Forms\CategoryForm;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public CategoryForm $form;

    public ?Category $category = null;

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function parentCategories(): Collection
    {
        return Category::query()
            ->system()
            ->active()
            ->ofType($this->form->type)
            ->when($this->category, fn ($query) => $query->where('id', '!=', $this->category->id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[On('panels.administrator.category.edit.assign-data')]
    public function assignData(Category $category): void
    {
        abort_unless($category->is_system && $category->business_id === null, 403);

        $this->category = $category;
        $this->form->setModel($this->category);
        $this->resetValidation();
        unset($this->parentCategories);

        Flux::modal('category.edit')->show();
    }

    public function save(): void
    {
        abort_unless($this->category?->is_system && $this->category->business_id === null, 403);

        $this->form->update();
        $this->reset('category');

        $this->dispatch('panels.administrator.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.category_updated'));
    }
};
?>

<flux:modal name="category.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_category') }}</flux:heading>
    </div>

    @if ($category)
        <flux:callout icon="folder-tree" variant="secondary" inline>
            {{ $category->name }}
            <flux:badge size="sm" :color="$category->type->badgeColor()" class="ms-2">
                {{ $category->type->label() }}
            </flux:badge>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.category_type') }}</flux:label>
            <flux:input :value="$category?->type?->label()" readonly />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.parent_category') }}</flux:label>
            <flux:select wire:model="form.parent_id" searchable variant="listbox" placeholder="{{ __('general.no_parent_category') }}" clearable>
                <flux:select.option value="">{{ __('general.no_parent_category') }}</flux:select.option>
                @foreach ($this->parentCategories as $parent)
                    <flux:select.option value="{{ $parent->id }}" wire:key="edit-cat-parent-{{ $parent->id }}">
                        {{ $parent->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.parent_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.category_code') }}</flux:label>
            <flux:input wire:model="form.code" placeholder="{{ __('general.category_code_placeholder') }}" dir="ltr" />
            <flux:error name="form.code" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" placeholder="{{ __('general.category_name_placeholder') }}" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.slug') }}</flux:label>
            <flux:input wire:model="form.slug" placeholder="{{ __('general.slug_auto_hint') }}" clearable dir="ltr" />
            <flux:error name="form.slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.description') }}</flux:label>
            <flux:textarea wire:model="form.description" rows="3" />
            <flux:error name="form.description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.sort_order') }}</flux:label>
            <flux:input type="number" wire:model="form.sort_order" dir="ltr" />
            <flux:error name="form.sort_order" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" />
            <flux:error name="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
