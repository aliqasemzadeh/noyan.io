<?php

use App\Actions\Business\UpdateBusinessInvoiceSettingsAction;
use App\Livewire\Forms\BusinessInvoiceSettingsForm;
use App\Models\Business;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Business $business = null;

    public BusinessInvoiceSettingsForm $invoiceSettingsForm;

    #[On('panels.administrator.business.invoice-settings.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;
        $this->invoiceSettingsForm->setModel($business);
        $this->resetValidation();

        Flux::modal('business.invoice-settings')->show();
    }

    public function save(UpdateBusinessInvoiceSettingsAction $action): void
    {
        $this->invoiceSettingsForm->update($action);

        Flux::modals()->close();
        Flux::toast(__('general.invoice_settings_saved'));
    }
};
?>

<flux:modal name="business.invoice-settings" flyout position="right" class="space-y-6 md:w-[32rem]">
    <div>
        <flux:heading size="lg">{{ __('general.invoice_settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.invoice_settings_hint') }}</flux:text>
    </div>

    @if ($business)
        <flux:callout icon="building-2" variant="secondary" inline>
            {{ $business->name }}
            —
            {{ __('general.sms_wallet_balance') }}:
            <span dir="ltr">{{ number_format((float) $business->sms_wallet_balance) }}</span>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <x-business.invoice-settings-fields form-prefix="invoiceSettingsForm" />

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>
