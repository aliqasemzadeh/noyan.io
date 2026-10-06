<?php

use App\Livewire\Forms\Accounting\FiscalYearForm;
use App\Models\Accounting\FiscalYear;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public FiscalYearForm $form;

    public ?FiscalYear $fiscalYear = null;

    #[On('panels.accounting.fiscal-year.edit.assign-data')]
    public function assignData(FiscalYear $fiscalYear): void
    {
        abort_unless(
            (int) $fiscalYear->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->fiscalYear = $fiscalYear;
        $this->form->setModel($this->fiscalYear);
        $this->resetValidation();

        Flux::modal('fiscal-year.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();
        $this->reset('fiscalYear');

        $this->dispatch('panels.accounting.fiscal-year.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.fiscal_year_updated'));
    }
};
?>

<flux:modal name="fiscal-year.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_fiscal_year') }}</flux:heading>
    </div>

    @if ($fiscalYear)
        <flux:callout icon="calendar" variant="secondary" inline>
            {{ $fiscalYear->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" />
            <flux:error name="form.name" />
        </flux:field>

        @if (\App\Support\LocaleDate::usesJalali())
            <x-date-picker wire:model="form.start_date" name="form.start_date" :label="__('general.fiscal_year_start_date')" required />
            <x-date-picker wire:model="form.end_date" name="form.end_date" :label="__('general.fiscal_year_end_date')" required />
        @else
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
        @endif

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
