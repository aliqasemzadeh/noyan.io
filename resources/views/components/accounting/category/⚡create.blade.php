<?php

use App\Enums\CategoryType;
use App\Livewire\Forms\CustomCategoryForm;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public CustomCategoryForm $form;

    public string $defaultType = '';

    public ?Category $parent = null;

    public function mount(string $type = ''): void
    {
        $this->defaultType = $type;

        if ($type !== '') {
            $this->form->type = $type;
        }
    }

    #[On('panels.accounting.category.create.assign-data')]
    public function assignData(?int $parent = null): void
    {
        $businessId = Auth::user()?->current_business_id;
        abort_if($businessId === null, 403);

        $this->form->reset();
        $this->resetValidation();
        $this->parent = null;

        if ($parent !== null) {
            $this->parent = Category::query()
                ->availableToBusiness($businessId)
                ->where('is_active', true)
                ->find($parent);

            abort_if($this->parent === null, 403);

            $this->form->type = $this->parent->type->value;
            $this->form->parent_id = $this->parent->id;
        } elseif ($this->defaultType !== '') {
            $this->form->type = $this->defaultType;
        }

        unset($this->parentCategories);

        Flux::modal('accounting.category.create')->show();
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

        $this->reset('parent');

        $this->dispatch('panels.accounting.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.category_created', ['name' => $category->name]));
    }
};
?>

<flux:modal name="accounting.category.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">
            {{ $parent ? __('general.create_subcategory') : __('general.create_category') }}
        </flux:heading>
        <flux:text class="mt-1">{{ __('general.create_custom_category_hint') }}</flux:text>
    </div>

    @if ($parent)
        <flux:callout icon="folder" variant="secondary" inline>
            {{ $parent->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.category_type') }}</flux:label>
            <flux:select wire:model.live="form.type" searchable variant="listbox" :disabled="$parent !== null">
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
            <flux:select wire:model="form.parent_id" searchable variant="listbox" placeholder="{{ __('general.no_parent_category') }}" clearable :disabled="$parent !== null">
                <flux:select.option value="">{{ __('general.no_parent_category') }}</flux:select.option>
                @foreach ($this->parentCategories as $parentOption)
                    <flux:select.option value="{{ $parentOption['id'] }}" wire:key="biz-create-parent-{{ $parentOption['id'] }}">
                        {{ str_repeat('— ', $parentOption['depth']) }}{{ $parentOption['name'] }}
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
