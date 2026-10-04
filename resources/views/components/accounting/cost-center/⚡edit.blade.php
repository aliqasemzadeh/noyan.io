<?php

use App\Livewire\Forms\Accounting\CostCenterForm;
use App\Models\Accounting\CostCenter;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public CostCenterForm $form;

    public ?CostCenter $costCenter = null;

    #[On('panels.accounting.cost-center.edit.assign-data')]
    public function assignData(CostCenter $costCenter): void
    {
        abort_unless(
            (int) $costCenter->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->costCenter = $costCenter;
        $this->form->setModel($this->costCenter);
        $this->resetValidation();

        Flux::modal('cost-center.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();
        $this->reset('costCenter');

        $this->dispatch('panels.accounting.cost-center.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cost_center_updated'));
    }
};
?>

<flux:modal name="cost-center.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_cost_center') }}</flux:heading>
    </div>

    @if ($costCenter)
        <flux:callout icon="layers" variant="secondary" inline>
            {{ $costCenter->name }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.code') }}</flux:label>
            <flux:input wire:model="form.code" clearable dir="ltr" />
            <flux:error name="form.code" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.name') }}</flux:label>
            <flux:input wire:model="form.name" />
            <flux:error name="form.name" />
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
