<?php

use App\Models\Catalog\ProductCategory;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?ProductCategory $category = null;

    #[On('panels.accounting.catalog.category.delete.assign-data')]
    public function assignData(ProductCategory $category): void
    {
        abort_unless(
            (int) $category->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->category = $category;

        Flux::modal('catalog.category.delete')->show();
    }

    public function delete(): void
    {
        if ($this->category === null) {
            return;
        }

        abort_unless(
            (int) $this->category->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->category->delete();
        $this->reset('category');

        $this->dispatch('panels.accounting.catalog.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.product_category_deleted'));
    }
};
?>

<flux:modal name="catalog.category.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_product_category_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($category)
            <flux:callout icon="layers" variant="secondary" inline>
                {{ $category->name }}
            </flux:callout>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
        </div>
    </form>
</flux:modal>
