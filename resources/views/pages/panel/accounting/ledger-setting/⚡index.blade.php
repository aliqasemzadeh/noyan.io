<?php

use App\Actions\Business\UpdateBusinessLedgerSettingsAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Livewire\Forms\BusinessLedgerSettingsForm;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public BusinessLedgerSettingsForm $form;

    public function mount(): void
    {
        $user = Auth::user();
        $user?->ensureCurrentBusiness();

        $businessId = $user?->current_business_id;

        abort_if($businessId === null, 403);

        $business = Business::query()->findOrFail($businessId);
        BusinessLedgerSetting::forBusiness($business);
        $this->form->setModel($business);
    }

    public function save(UpdateBusinessLedgerSettingsAction $action): void
    {
        $this->form->update($action);

        Flux::toast(__('general.ledger_settings_saved'));
    }
};
?>

<x-slot name="title">{{ __('general.ledger_settings') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.ledger_settings') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl">{{ __('general.ledger_settings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.ledger_settings_hint') }}</flux:text>
        </div>
    </div>

    <flux:card class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.voucher_numbering_rules') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.voucher_numbering_rules_hint') }}</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select
                    wire:model="form.voucher_numbering_mode"
                    :label="__('general.voucher_numbering_mode')"
                    variant="listbox"
                >
                    @foreach (VoucherNumberingMode::cases() as $mode)
                        <flux:select.option :value="$mode->value">{{ $mode->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="form.voucher_prefix"
                    :label="__('general.voucher_prefix')"
                    :placeholder="__('general.voucher_prefix_placeholder')"
                />

                <flux:input
                    wire:model.number="form.voucher_number_pad"
                    type="number"
                    min="1"
                    max="10"
                    :label="__('general.voucher_number_pad')"
                />

                <flux:select
                    wire:model="form.default_voucher_status"
                    :label="__('general.default_voucher_status')"
                    variant="listbox"
                >
                    <flux:select.option :value="JournalEntryStatus::Draft->value">
                        {{ JournalEntryStatus::Draft->label() }}
                    </flux:select.option>
                    <flux:select.option :value="JournalEntryStatus::Posted->value">
                        {{ JournalEntryStatus::Posted->label() }}
                    </flux:select.option>
                </flux:select>
            </div>

            <flux:separator />

            <div>
                <flux:heading size="lg">{{ __('general.auto_posting_policy') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.auto_posting_policy_hint') }}</flux:text>
            </div>

            <flux:select
                wire:model="form.auto_post_mode"
                :label="__('general.auto_post_mode')"
                variant="listbox"
                class="max-w-md"
            >
                @foreach (AutoPostMode::cases() as $mode)
                    <flux:select.option :value="$mode->value">{{ $mode->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" variant="primary" color="orange" class="w-full sm:w-auto">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
