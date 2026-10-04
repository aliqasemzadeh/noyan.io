<?php

use App\Models\Accounting\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Project $project = null;

    #[On('panels.accounting.project.delete.assign-data')]
    public function assignData(Project $project): void
    {
        abort_unless(
            (int) $project->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->project = $project;

        Flux::modal('project.delete')->show();
    }

    public function delete(): void
    {
        if ($this->project === null) {
            return;
        }

        abort_unless(
            (int) $this->project->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->project->delete();
        $this->reset('project');

        $this->dispatch('panels.accounting.project.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.project_deleted'));
    }
};
?>

<flux:modal name="project.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_project_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($project)
            <flux:callout icon="folder-kanban" variant="secondary" inline>
                {{ $project->name }}
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
