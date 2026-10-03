<?php

use App\Enums\CategoryType;
use App\Livewire\Forms\CustomCategoryForm;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public CustomCategoryForm $form;

    public function mount(string $type = ''): void
    {
        if ($type !== '') {
            $this->form->type = $type;
        }
    }

    /**
     * @return Collection<int, array{id: int, name: string, path: string, depth: int, is_leaf: bool, is_system: bool}>
     */
    #[Computed]
    public function parentCategories(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Category::selectOptionsForBusiness($businessId, CategoryType::from($this->form->type));
    }

    public function updatedFormType(): void
    {
        $this->form->parent_id = null;
        unset($this->parentCategories);
    }

    public function save(): void
    {
        $category = $this->form->store();

        $this->dispatch('panels.accounting.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.category_created', ['name' => $category->name]));
    }
};
?>

<flux:modal name="accounting.category.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_category') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.create_custom_category_hint') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.category_type') }}</flux:label>
            <flux:select wire:model.live="form.type" searchable variant="listbox">
                @foreach (CategoryType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="biz-create-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.parent_category') }}</flux:label>
            <flux:select wire:model="form.parent_id" searchable variant="listbox" placeholder="{{ __('general.no_parent_category') }}" clearable>
                <flux:select.option value="">{{ __('general.no_parent_category') }}</flux:select.option>
                @foreach ($this->parentCategories as $parent)
                    <flux:select.option value="{{ $parent['id'] }}" wire:key="biz-create-parent-{{ $parent['id'] }}">
                        {{ str_repeat('— ', $parent['depth']) }}{{ $parent['name'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.parent_id" />
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
