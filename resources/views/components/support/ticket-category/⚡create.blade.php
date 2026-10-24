<?php

use App\Livewire\Forms\TicketCategoryForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public TicketCategoryForm $form;

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('panels.administrator.support.ticket.category.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.ticket_category_created'));
    }
};
?>

<flux:modal name="ticket-category.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_ticket_category') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.title') }}</flux:label>
            <flux:input wire:model="form.title" />
            <flux:error name="form.title" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.icon') }}</flux:label>
            <flux:input wire:model="form.icon" dir="ltr" />
            <flux:error name="form.icon" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.description') }}</flux:label>
            <flux:textarea wire:model="form.description" rows="3" />
            <flux:error name="form.description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.sort_order') }}</flux:label>
            <flux:input type="number" wire:model="form.sort_order" min="0" dir="ltr" />
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
