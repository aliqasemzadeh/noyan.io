<?php

use App\Livewire\Forms\Accounting\GlAccountForm;
use App\Models\Accounting\GlAccount;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public GlAccountForm $form;

    #[On('panels.accounting.gl-account.delete.assign-data')]
    public function assignData(int $glAccountId): void
    {
        $account = GlAccount::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->findOrFail($glAccountId);

        $this->form->setModel($account);
        $this->resetValidation();

        Flux::modal('gl-account.delete')->show();
    }

    public function destroy(): void
    {
        $name = $this->form->glAccount?->name ?? '';
        $this->form->delete();

        $this->dispatch('panels.accounting.gl-account.index.table');
        Flux::modals()->close();
        Flux::toast(__('general.gl_account_deleted', ['name' => $name]));
    }
};
?>

<flux:modal name="gl-account.delete" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.delete_gl_account') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('general.delete_gl_account_warning', ['name' => $form->glAccount?->name ?? '']) }}
        </flux:text>
    </div>

    <flux:button type="button" variant="primary" color="red" class="w-full" wire:click="destroy" wire:confirm="{{ __('general.are_you_sure') }}">
        {{ __('general.delete') }}
    </flux:button>
</flux:modal>
