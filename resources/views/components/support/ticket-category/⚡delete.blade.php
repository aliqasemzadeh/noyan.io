<?php

use App\Models\TicketCategory;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?TicketCategory $category = null;

    #[On('panels.administrator.support.ticket.category.delete.assign-data')]
    public function assignData(TicketCategory $category): void
    {
        $this->category = $category;
        $this->resetValidation();

        Flux::modal('ticket-category.delete')->show();
    }

    public function delete(): void
    {
        if ($this->category === null) {
            return;
        }

        if ($this->category->isInUse()) {
            Flux::toast(__('general.ticket_category_in_use'), variant: 'danger');

            return;
        }

        $this->category->delete();

        $this->reset('category');

        $this->dispatch('panels.administrator.support.ticket.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.ticket_category_deleted'));
    }
};
?>

<flux:modal name="ticket-category.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_ticket_category_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($category)
            <flux:callout icon="folder-kanban" variant="secondary" inline>
                {{ $category->title }}
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
