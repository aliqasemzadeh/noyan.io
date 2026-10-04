<?php

use App\Models\Accounting\CostCenter;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?CostCenter $costCenter = null;

    #[On('panels.accounting.cost-center.delete.assign-data')]
    public function assignData(CostCenter $costCenter): void
    {
        abort_unless(
            (int) $costCenter->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->costCenter = $costCenter;

        Flux::modal('cost-center.delete')->show();
    }

    public function delete(): void
    {
        if ($this->costCenter === null) {
            return;
        }

        abort_unless(
            (int) $this->costCenter->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->costCenter->delete();
        $this->reset('costCenter');

        $this->dispatch('panels.accounting.cost-center.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.cost_center_deleted'));
    }
};
?>

<flux:modal name="cost-center.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_cost_center_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($costCenter)
            <flux:callout icon="layers" variant="secondary" inline>
                {{ $costCenter->name }}
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
