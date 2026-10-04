<?php

use App\Models\Accounting\FiscalYear;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?FiscalYear $fiscalYear = null;

    #[On('panels.accounting.fiscal-year.delete.assign-data')]
    public function assignData(FiscalYear $fiscalYear): void
    {
        abort_unless(
            (int) $fiscalYear->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->fiscalYear = $fiscalYear;

        Flux::modal('fiscal-year.delete')->show();
    }

    public function delete(): void
    {
        if ($this->fiscalYear === null) {
            return;
        }

        abort_unless(
            (int) $this->fiscalYear->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->fiscalYear->delete();
        $this->reset('fiscalYear');

        $this->dispatch('panels.accounting.fiscal-year.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.fiscal_year_deleted'));
    }
};
?>

<flux:modal name="fiscal-year.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_fiscal_year_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($fiscalYear)
            <flux:callout icon="calendar" variant="secondary" inline>
                {{ $fiscalYear->name }}
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
