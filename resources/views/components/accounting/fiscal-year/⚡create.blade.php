<?php

use App\Livewire\Forms\Accounting\FiscalYearForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public FiscalYearForm $form;

    public function save(): void
    {
        $fiscalYear = $this->form->store();

        $this->dispatch('panels.accounting.fiscal-year.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.fiscal_year_created', ['name' => $fiscalYear->name]));
    }
};
?>

<flux:modal name="fiscal-year.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_fiscal_year') }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" />
            <flux:error name="form.name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.fiscal_year_start_date') }}</flux:label>
            <flux:input type="date" wire:model="form.start_date" dir="ltr" />
            <flux:error name="form.start_date" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.fiscal_year_end_date') }}</flux:label>
            <flux:input type="date" wire:model="form.end_date" dir="ltr" />
            <flux:error name="form.end_date" />
        </flux:field>

        <flux:field variant="inline">
            <flux:label>{{ __('general.fiscal_year_is_closed') }}</flux:label>
            <flux:switch wire:model.live="form.is_closed" />
            <flux:error name="form.is_closed" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
