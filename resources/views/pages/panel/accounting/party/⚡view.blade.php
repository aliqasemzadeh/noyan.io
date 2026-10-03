<?php

use App\Models\Accounting\Party;
use App\Models\Accounting\PartyContact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public Party $party;

    public function mount(Party $party): void
    {
        Auth::user()?->ensureCurrentBusiness();

        abort_unless(
            (int) $party->business_id === (int) Auth::user()?->current_business_id,
            403
        );

        $this->party = $party;
    }

    #[On('panels.accounting.party.view.refresh')]
    public function refreshParty(): void
    {
        if (! Party::query()->whereKey($this->party->id)->exists()) {
            $this->redirect(route('accounting.parties.index'), navigate: true);

            return;
        }

        $this->party->refresh();
        unset($this->contacts, $this->formattedBalance, $this->formattedCreditLimit, $this->formattedCreatedAt);
    }

    #[On('panels.accounting.party.view.contacts')]
    public function refreshContacts(): void
    {
        unset($this->contacts);
        $this->party->refresh();
    }

    #[On('panels.accounting.party.index.table')]
    public function handlePartyListChanged(): void
    {
        if (! Party::query()->whereKey($this->party->id)->exists()) {
            $this->redirect(route('accounting.parties.index'), navigate: true);
        }
    }

    /**
     * @return Collection<int, PartyContact>
     */
    #[Computed]
    public function contacts(): Collection
    {
        return $this->party->contacts()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function formattedBalance(): string
    {
        return $this->formatAmount((string) $this->party->balance);
    }

    #[Computed]
    public function formattedCreditLimit(): string
    {
        return $this->formatAmount((string) $this->party->credit_limit);
    }

    #[Computed]
    public function formattedCreatedAt(): string
    {
        return Jalalian::fromDateTime($this->party->created_at)->format('Y/m/d H:i');
    }

    protected function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }
};
?>

<x-slot name="title">{{ $party->name }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.parties.index')" wire:navigate>
                {{ __('general.parties') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $party->name }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">
                    {{ $party->name }}
                </flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$party->type->badgeColor()">
                        {{ $party->type->label() }}
                    </flux:badge>
                    @if ($party->is_customer)
                        <flux:badge size="sm" color="teal">{{ __('general.customer') }}</flux:badge>
                    @endif
                    @if ($party->is_supplier)
                        <flux:badge size="sm" color="amber">{{ __('general.supplier') }}</flux:badge>
                    @endif
                    <flux:badge size="sm" :color="$party->is_active ? 'green' : 'zinc'">
                        {{ $party->is_active ? __('general.active') : __('general.inactive') }}
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
                        wire:click="$dispatch('panels.accounting.party.edit.assign-data', { party: {{ $party->id }} })"
                    >
                        {{ __('general.edit') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item
                        variant="danger"
                        icon="trash"
                        wire:click="$dispatch('panels.accounting.party.delete.assign-data', { party: {{ $party->id }} })"
                    >
                        {{ __('general.delete') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.party_details') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.legal_name') }}</flux:text>
                    <flux:text class="font-medium">{{ $party->legal_name ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.national_id') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->national_id ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.economic_code') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->economic_code ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.balance') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedBalance }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.credit_limit') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $this->formattedCreditLimit }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.created_at') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->formattedCreatedAt }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('general.contact_info') }}</flux:heading>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.mobile') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->mobile ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.phone') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->phone ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.email') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->email ?: '—' }}</flux:text>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('general.postal_code') }}</flux:text>
                    <flux:text class="font-medium" dir="ltr">{{ $party->postal_code ?: '—' }}</flux:text>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <flux:text class="text-zinc-500">{{ __('general.address') }}</flux:text>
                    <flux:text class="max-w-xs text-end font-medium">{{ $party->address ?: '—' }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('general.party_contacts') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.party_contacts_hint') }}</flux:text>
            </div>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                wire:click="$dispatch('panels.accounting.party.contact.create.assign-data', { party: {{ $party->id }} })"
            >
                {{ __('general.create_party_contact') }}
            </flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.contact_position') }}</flux:table.column>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.email') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_primary_contact') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->contacts as $contact)
                    <flux:table.row :key="$contact->id">
                        <flux:table.cell class="font-medium">{{ $contact->name }}</flux:table.cell>
                        <flux:table.cell>{{ $contact->position ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $contact->mobile ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $contact->email ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($contact->is_primary)
                                <flux:badge size="sm" color="teal">{{ __('general.primary') }}</flux:badge>
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.party.contact.edit.assign-data', { contact: {{ $contact->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.party.contact.delete.assign-data', { contact: {{ $contact->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            {{ __('general.no_party_contacts') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.party.edit :key="'party-edit-view-'.$party->id" />
    <livewire:accounting.party.delete :key="'party-delete-view-'.$party->id" />
    <livewire:accounting.party.contact.create :key="'party-contact-create-'.$party->id" />
    <livewire:accounting.party.contact.edit :key="'party-contact-edit-'.$party->id" />
    <livewire:accounting.party.contact.delete :key="'party-contact-delete-'.$party->id" />
</div>
