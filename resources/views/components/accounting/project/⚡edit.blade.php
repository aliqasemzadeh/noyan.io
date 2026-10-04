<?php

use App\Livewire\Forms\Accounting\ProjectForm;
use App\Models\Accounting\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ProjectForm $form;

    public ?Project $project = null;

    #[On('panels.accounting.project.edit.assign-data')]
    public function assignData(Project $project): void
    {
        abort_unless(
            (int) $project->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->project = $project;
        $this->form->setModel($this->project);
        $this->resetValidation();

        Flux::modal('project.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();
        $this->reset('project');

        $this->dispatch('panels.accounting.project.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.project_updated'));
    }
};
?>

<flux:modal name="project.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_project') }}</flux:heading>
    </div>

    @if ($project)
        <flux:callout icon="folder-kanban" variant="secondary" inline>
            {{ $project->name }}
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
