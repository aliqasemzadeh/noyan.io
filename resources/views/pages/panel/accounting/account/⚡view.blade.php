<?php

use App\Models\Accounting\Account;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public Account $account;

    public function mount(Account $account): void
    {
        Auth::user()?->ensureCurrentBusiness();

        abort_unless(
            (int) $account->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->account = $account->loadMissing('currency');
    }

    #[On('panels.accounting.account.view.refresh')]
    public function refreshAccount(): void
    {
        $this->account->refresh()->loadMissing('currency');
        unset($this->formattedBalance, $this->formattedCreatedAt);
    }

    #[On('panels.accounting.account.index.table')]
    public function handleAccountDeleted(): void
    {
        if (! Account::query()->whereKey($this->account->id)->exists()) {
            $this->redirect(route('accounting.accounts.index'), navigate: true);
        }
    }

    #[Computed]
    public function formattedBalance(): string
    {
        /** @var Currency|null $currency */
        $currency = $this->account->currency;

        if ($currency === null) {
            return (string) $this->account->opening_balance;
        }

        return $currency->formatAmount((string) $this->account->opening_balance);
    }

    #[Computed]
    public function formattedCreatedAt(): string
    {
        return Jalalian::fromDateTime($this->account->created_at)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ $account->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.accounts.index')" wire:navigate>
                {{ __('general.cash_and_bank_accounts') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $account->name }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">
                    {{ $account->name }}
                </flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$account->sub_type->badgeColor()">
                        {{ $account->sub_type->label() }}
                    </flux:badge>
                    <flux:badge size="sm" color="zinc">
                        {{ $account->type->label() }}
                    </flux:badge>
                    <flux:badge size="sm" :color="$account->is_active ? 'green' : 'zinc'">
                        {{ $account->is_active ? __('general.active') : __('general.inactive') }}
                    </flux:badge>
                </div>
            </div>

            <flux:dropdown>
                <flux:button icon:trailing="chevron-down" variant="primary" color="zinc">
                    {{ __('general.options') }}
                </flux:button>
                <flux:menu>
                    <flux:menu.item
                        icon="pencil"
                        wire:click="$dispatch('panels.accounting.account.edit.assign-data', { account: {{ $account->id }} })"
                    >
                        {{ __('general.edit') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item
                        variant="danger"
                        icon="trash"
                        wire:click="$dispatch('panels.accounting.account.delete.assign-data', { account: {{ $account->id }} })"
                    >
                        {{ __('general.delete') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.account_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.currency') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">
                        {{ $account->currency?->code }} — {{ $account->currency?->name }}
                    </flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.opening_balance') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedBalance }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account_sub_type') }}</flux:text>
                    <flux:text class="font-medium">{{ $account->sub_type->label() }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account_type') }}</flux:text>
                    <flux:text class="font-medium">{{ $account->type->label() }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.created_at') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->formattedCreatedAt }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.bank_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.bank_name') }}</flux:text>
                    <flux:text class="font-medium">{{ $account->bank_name ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.account_number') }}</flux:text>
                    @if ($account->account_number)
                        <flux:input readonly copyable :value="$account->account_number" dir="ltr" class="max-w-56" />
                    @else
                        <flux:text class="font-medium">—</flux:text>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.card_number') }}</flux:text>
                    @if ($account->card_number)
                        <flux:input readonly copyable :value="$account->card_number" dir="ltr" class="max-w-56" />
                    @else
                        <flux:text class="font-medium">—</flux:text>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.iban') }}</flux:text>
                    @if ($account->iban)
                        <flux:input readonly copyable :value="$account->iban" dir="ltr" class="max-w-64" />
                    @else
                        <flux:text class="font-medium">—</flux:text>
                    @endif
                </div>
            </div>
        </flux:card>
    </div>

    @if ($account->note)
        <flux:card class="space-y-2">
            <flux:heading size="lg">{{ __('general.note') }}</flux:heading>
            <flux:text>{{ $account->note }}</flux:text>
        </flux:card>
    @endif

    <livewire:accounting.account.edit :key="'account-edit-view-'.$account->id" />
    <livewire:accounting.account.delete :key="'account-delete-view-'.$account->id" />
</div>
