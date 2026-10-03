<?php

use App\Enums\PartyType;
use App\Models\Accounting\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $roleFilter = '';

    #[Url]
    public string $statusFilter = '';

    public function mount(): void
    {
        Auth::user()?->ensureCurrentBusiness();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[On('panels.accounting.party.index.table')]
    public function refreshTable(): void
    {
        unset($this->parties);
    }

    /**
     * @return LengthAwarePaginator<int, Party>
     */
    #[Computed]
    public function parties(): LengthAwarePaginator
    {
        $businessId = Auth::user()?->current_business_id;

        return Party::query()
            ->withCount('contacts')
            ->when($businessId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('legal_name', 'like', $search)
                        ->orWhere('national_id', 'like', $search)
                        ->orWhere('economic_code', 'like', $search)
                        ->orWhere('mobile', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->roleFilter === 'customer', fn ($query) => $query->where('is_customer', true))
            ->when($this->roleFilter === 'supplier', fn ($query) => $query->where('is_supplier', true))
            ->when($this->roleFilter === 'both', fn ($query) => $query->where('is_customer', true)->where('is_supplier', true))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(15);
    }

    public function formatCreatedAt(Party $party): string
    {
        return Jalalian::fromDateTime($party->created_at)->format('Y/m/d H:i');
    }

    public function formatAmount(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return number_format((float) $amount);
        }

        $normalized = rtrim(rtrim($amount, '0'), '.') ?: '0';

        return number_format((float) $normalized, substr_count($normalized, '.') ? strlen(explode('.', $normalized)[1]) : 0);
    }
};
?>

<x-slot name="title">{{ __('general.parties') }} - {{ __('general.app_name') }}</x-slot>

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
                {{ __('general.parties') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('general.parties') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.parties_page_hint') }}</flux:text>
            </div>

            <flux:modal.trigger name="party.create">
                <flux:button variant="primary" color="teal" icon="plus">
                    {{ __('general.create_party') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (auth()->user()->current_business_id === null)
        <flux:callout icon="building" variant="secondary">
            {{ __('general.no_business_yet') }}
        </flux:callout>
    @endif

    <flux:card>
        <div class="mb-4 grid gap-3 md:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
                class="md:col-span-1"
            />

            <flux:select wire:model.live="typeFilter" searchable variant="listbox" placeholder="{{ __('general.all_party_types') }}">
                <flux:select.option value="">{{ __('general.all_party_types') }}</flux:select.option>
                @foreach (PartyType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}" wire:key="filter-type-{{ $type->value }}">
                        {{ $type->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="roleFilter" searchable variant="listbox" placeholder="{{ __('general.all_party_roles') }}">
                <flux:select.option value="">{{ __('general.all_party_roles') }}</flux:select.option>
                <flux:select.option value="customer">{{ __('general.is_customer') }}</flux:select.option>
                <flux:select.option value="supplier">{{ __('general.is_supplier') }}</flux:select.option>
                <flux:select.option value="both">{{ __('general.customer_and_supplier') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="statusFilter" searchable variant="listbox" placeholder="{{ __('general.all_statuses') }}">
                <flux:select.option value="">{{ __('general.all_statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('general.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('general.inactive') }}</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->parties">
            <flux:table.columns>
                <flux:table.column>{{ __('general.name') }}</flux:table.column>
                <flux:table.column>{{ __('general.party_type') }}</flux:table.column>
                <flux:table.column>{{ __('general.roles') }}</flux:table.column>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.balance') }}</flux:table.column>
                <flux:table.column>{{ __('general.contacts') }}</flux:table.column>
                <flux:table.column>{{ __('general.is_active') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->parties as $party)
                    <flux:table.row :key="$party->id">
                        <flux:table.cell>
                            <a
                                href="{{ route('accounting.parties.view', $party) }}"
                                wire:navigate
                                class="font-medium text-teal-700 hover:underline dark:text-teal-400"
                            >
                                {{ $party->name }}
                            </a>
                            @if ($party->legal_name && $party->legal_name !== $party->name)
                                <flux:text size="sm" class="mt-0.5 block text-zinc-500">{{ $party->legal_name }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$party->type->badgeColor()">
                                {{ $party->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($party->is_customer)
                                    <flux:badge size="sm" color="teal">{{ __('general.customer') }}</flux:badge>
                                @endif
                                @if ($party->is_supplier)
                                    <flux:badge size="sm" color="amber">{{ __('general.supplier') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $party->mobile ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span dir="ltr">{{ $this->formatAmount((string) $party->balance) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $party->contacts_count }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$party->is_active ? 'green' : 'zinc'">
                                {{ $party->is_active ? __('general.active') : __('general.inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($party) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:tooltip content="{{ __('general.view_party') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="zinc"
                                        icon="eye"
                                        icon:variant="outline"
                                        :href="route('accounting.parties.view', $party)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.edit') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="blue"
                                        icon="pencil"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.party.edit.assign-data', { party: {{ $party->id }} })"
                                    />
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('general.delete') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="$dispatch('panels.accounting.party.delete.assign-data', { party: {{ $party->id }} })"
                                    />
                                </flux:tooltip>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9">
                            {{ __('general.no_parties') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <livewire:accounting.party.create :key="'party-create'" />
    <livewire:accounting.party.edit :key="'party-edit'" />
    <livewire:accounting.party.delete :key="'party-delete'" />
</div>
