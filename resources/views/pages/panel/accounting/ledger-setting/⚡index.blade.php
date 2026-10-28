<?php

use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
use App\Actions\Business\UpdateBusinessLedgerSettingsAction;
use App\Actions\Ledger\BackfillBusinessGlAction;
use App\Actions\Ledger\ConvertManualSettlementJournalsAction;
use App\Actions\Ledger\SyncBusinessGlAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Livewire\Forms\BusinessLedgerSettingsForm;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public BusinessLedgerSettingsForm $form;

    public string $tab = 'numbering';

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

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function save(UpdateBusinessLedgerSettingsAction $action): void
    {
        $this->form->update($action);

        Flux::toast(__('general.ledger_settings_saved'));
    }

    public function syncDefaults(SyncBusinessLedgerDefaultAccountsAction $action): void
    {
        $business = $this->form->business;
        abort_if($business === null, 403);

        $action->handle($business, overwrite: true);
        $this->form->setModel($business->fresh() ?? $business);

        Flux::toast(__('general.ledger_defaults_synced'));
    }

    public function backfillMissingJournals(BackfillBusinessGlAction $action): void
    {
        [$business, $user] = $this->requireBusinessContext();

        $forcePost = $this->form->auto_post_mode === AutoPostMode::Realtime->value;
        $stats = $action->handle($business, $user, forcePost: $forcePost);

        Flux::toast(__('general.ledger_backfill_done', [
            'invoices' => $stats['invoices']['posted'],
            'transactions' => $stats['transactions']['posted'],
            'failed' => $stats['invoices']['failed'] + $stats['transactions']['failed'],
        ]));
    }

    public function convertManualJournals(ConvertManualSettlementJournalsAction $action): void
    {
        [$business, $user] = $this->requireBusinessContext();

        $stats = $action->handle($business, $user);

        Flux::toast(__('general.ledger_convert_journals_done', [
            'converted' => $stats['converted'],
            'skipped' => $stats['skipped'],
            'failed' => $stats['failed'],
        ]));
    }

    public function syncGlDocuments(SyncBusinessGlAction $action): void
    {
        [$business, $user] = $this->requireBusinessContext();

        $forcePost = $this->form->auto_post_mode === AutoPostMode::Realtime->value;
        $stats = $action->handle($business, $user, forcePost: $forcePost);

        Flux::toast(__('general.ledger_sync_gl_done', [
            'invoices' => $stats['resynced']['invoices'] + $stats['backfill']['invoices']['posted'],
            'transactions' => $stats['resynced']['transactions'] + $stats['backfill']['transactions']['posted'],
            'failed' => $stats['resynced']['failed']
                + $stats['backfill']['invoices']['failed']
                + $stats['backfill']['transactions']['failed'],
        ]));
    }

    /**
     * @return array{0: Business, 1: \App\Models\User}
     */
    protected function requireBusinessContext(): array
    {
        $user = Auth::user();
        $business = $this->form->business;

        abort_if($user === null || $business === null, 403);

        return [$business, $user];
    }

    /**
     * @return Collection<int, GlAccount>
     */
    #[Computed]
    public function codeLengthsLocked(): bool
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return true;
        }

        return GlAccount::withTrashed()
            ->where('business_id', $businessId)
            ->exists();
    }

    /**
     * @return Collection<int, GlAccount>
     */
    #[Computed]
    public function subsidiaryAccounts(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return GlAccount::query()
            ->where('business_id', $businessId)
            ->postable()
            ->active()
            ->orderBy('full_code')
            ->get(['id', 'full_code', 'name']);
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

    <flux:tabs wire:model="tab" variant="segmented">
        <flux:tab name="numbering" wire:click="setTab('numbering')">{{ __('general.voucher_numbering_rules') }}</flux:tab>
        <flux:tab name="auto_post" wire:click="setTab('auto_post')">{{ __('general.auto_posting_policy') }}</flux:tab>
        <flux:tab name="defaults" wire:click="setTab('defaults')">{{ __('general.default_accounts_mapping') }}</flux:tab>
    </flux:tabs>

    <flux:card class="space-y-6">
        <form wire:submit="save" class="space-y-6">
            @if ($tab === 'numbering')
                <div>
                    <flux:heading size="lg">{{ __('general.voucher_numbering_rules') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('general.voucher_numbering_rules_hint') }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.voucher_numbering_mode" :label="__('general.voucher_numbering_mode')" variant="listbox">
                        @foreach (VoucherNumberingMode::cases() as $mode)
                            <flux:select.option :value="$mode->value">{{ $mode->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.voucher_prefix" :label="__('general.voucher_prefix')" :placeholder="__('general.voucher_prefix_placeholder')" />

                    <flux:input wire:model.number="form.voucher_number_pad" type="number" min="1" max="10" :label="__('general.voucher_number_pad')" />

                    <flux:input wire:model.number="form.group_code_length" type="number" min="1" max="8" :label="__('general.gl_code_length_group')" :disabled="$this->codeLengthsLocked" />

                    <flux:input wire:model.number="form.control_code_length" type="number" min="1" max="8" :label="__('general.gl_code_length_control')" :disabled="$this->codeLengthsLocked" />

                    <flux:input wire:model.number="form.subsidiary_code_length" type="number" min="1" max="8" :label="__('general.gl_code_length_subsidiary')" :disabled="$this->codeLengthsLocked" />

                    <flux:select wire:model="form.default_voucher_status" :label="__('general.default_voucher_status')" variant="listbox">
                        <flux:select.option :value="JournalEntryStatus::Draft->value">{{ JournalEntryStatus::Draft->label() }}</flux:select.option>
                        <flux:select.option :value="JournalEntryStatus::Posted->value">{{ JournalEntryStatus::Posted->label() }}</flux:select.option>
                    </flux:select>
                </div>
            @endif

            @if ($tab === 'auto_post')
                <div>
                    <flux:heading size="lg">{{ __('general.auto_posting_policy') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('general.auto_posting_policy_hint') }}</flux:text>
                </div>

                <flux:select wire:model="form.auto_post_mode" :label="__('general.auto_post_mode')" variant="listbox" class="max-w-md">
                    @foreach (AutoPostMode::cases() as $mode)
                        <flux:select.option :value="$mode->value">{{ $mode->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div>
                        <flux:heading size="md">{{ __('general.ledger_gl_maintenance') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.ledger_gl_maintenance_hint') }}</flux:text>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:button
                            type="button"
                            variant="primary"
                            color="teal"
                            icon="plus"
                            wire:click="backfillMissingJournals"
                            wire:confirm="{{ __('general.ledger_backfill_confirm') }}"
                        >
                            {{ __('general.ledger_backfill_missing') }}
                        </flux:button>

                        <flux:button
                            type="button"
                            variant="primary"
                            color="cyan"
                            icon="arrow-left-right"
                            wire:click="convertManualJournals"
                            wire:confirm="{{ __('general.ledger_convert_journals_confirm') }}"
                        >
                            {{ __('general.ledger_convert_manual_journals') }}
                        </flux:button>

                        <flux:button
                            type="button"
                            variant="primary"
                            color="zinc"
                            icon="refresh-cw"
                            wire:click="syncGlDocuments"
                            wire:confirm="{{ __('general.ledger_sync_gl_confirm') }}"
                        >
                            {{ __('general.ledger_sync_gl') }}
                        </flux:button>
                    </div>
                </div>
            @endif

            @if ($tab === 'defaults')
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('general.default_accounts_mapping') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('general.default_accounts_mapping_hint') }}</flux:text>
                    </div>

                    <flux:button type="button" variant="primary" color="zinc" icon="refresh-cw" wire:click="syncDefaults" wire:confirm="{{ __('general.ledger_defaults_sync_confirm') }}">
                        {{ __('general.sync_default_accounts') }}
                    </flux:button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'default_accounts_receivable_id' => 'default_accounts_receivable',
                        'default_accounts_payable_id' => 'default_accounts_payable',
                        'default_sales_id' => 'default_sales',
                        'default_sales_discount_id' => 'default_sales_discount',
                        'default_vat_payable_id' => 'default_vat_payable',
                        'default_vat_receivable_id' => 'default_vat_receivable',
                        'default_inventory_id' => 'default_inventory',
                        'default_cogs_id' => 'default_cogs',
                        'default_cash_and_bank_id' => 'default_cash_and_bank',
                        'default_fx_gain_id' => 'default_fx_gain',
                        'default_fx_loss_id' => 'default_fx_loss',
                    ] as $field => $labelKey)
                        <flux:select wire:model="form.{{ $field }}" :label="__('general.'.$labelKey)" variant="listbox" searchable clearable>
                            <flux:select.option value="">{{ __('general.none') }}</flux:select.option>
                            @foreach ($this->subsidiaryAccounts as $account)
                                <flux:select.option :value="$account->id">
                                    <span dir="ltr">{{ $account->full_code }}</span> — {{ $account->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endforeach
                </div>
            @endif

            <flux:button type="submit" variant="primary" color="orange" class="w-full sm:w-auto">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
