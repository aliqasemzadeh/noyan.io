<?php

use App\Models\Catalog\Brand;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Brand $brand = null;

    #[On('panels.accounting.catalog.brand.delete.assign-data')]
    public function assignData(Brand $brand): void
    {
        abort_unless(
            (int) $brand->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->brand = $brand;

        Flux::modal('catalog.brand.delete')->show();
    }

    public function delete(): void
    {
        if ($this->brand === null) {
            return;
        }

        abort_unless(
            (int) $this->brand->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->brand->delete();
        $this->reset('brand');

        $this->dispatch('panels.accounting.catalog.brand.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.brand_deleted'));
    }
};
?>

<flux:modal name="catalog.brand.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_brand_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($brand)
            <flux:callout icon="tags" variant="secondary" inline>
                {{ $brand->name }}
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
