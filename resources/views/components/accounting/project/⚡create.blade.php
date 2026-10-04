<?php

use App\Livewire\Forms\Accounting\ProjectForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public ProjectForm $form;

    public function save(): void
    {
        $project = $this->form->store();

        $this->dispatch('panels.accounting.project.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.project_created', ['name' => $project->name]));
    }
};
?>

<flux:modal name="project.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_project') }}</flux:heading>
    </div>

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
