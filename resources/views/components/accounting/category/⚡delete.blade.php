<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Category $category = null;

    #[On('panels.accounting.category.delete.assign-data')]
    public function assignData(Category $category): void
    {
        $businessId = Auth::user()?->current_business_id;

        abort_if($category->is_system, 403);
        abort_unless($category->isOwnedByBusiness($businessId), 403);

        $this->category = $category;

        Flux::modal('accounting.category.delete')->show();
    }

    public function delete(): void
    {
        if ($this->category === null) {
            return;
        }

        $businessId = Auth::user()?->current_business_id;

        abort_if($this->category->is_system, 403);
        abort_unless($this->category->isOwnedByBusiness($businessId), 403);

        if ($this->category->children()->exists()) {
            Flux::toast(__('general.category_has_children_cannot_delete'), variant: 'danger');

            return;
        }

        if ($this->category->isInUse()) {
            Flux::toast(__('general.category_in_use_cannot_delete'), variant: 'danger');

            return;
        }

        $this->category->delete();
        $this->reset('category');

        $this->dispatch('panels.accounting.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.category_deleted'));
    }
};
?>

<flux:modal name="accounting.category.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_category_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($category)
            <flux:callout icon="folder-tree" variant="secondary" inline>
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
