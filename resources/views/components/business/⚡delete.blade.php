<?php

use App\Models\Business;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Business $business = null;

    #[On('panels.administrator.business.delete.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;

        Flux::modal('business.delete')->show();
    }

    public function delete(): void
    {
        if ($this->business) {
            $this->business->delete();
        }

        $this->reset('business');

        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_deleted'));
    }
};
?>

<flux:modal name="business.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_business_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($business)
            <flux:callout icon="box" variant="secondary" inline>
                {{ $business->name }}
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
