<?php

use App\Enums\Business\BusinessPanel;
use App\Enums\Business\BusinessRole;
use App\Enums\Support\TicketStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Ticket;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    public string $selectedBusinessId = '';

    public function mount(): void
    {
        $this->selectedBusinessId = (string) (Auth::user()?->current_business_id ?? '');
    }

    public function updatedSelectedBusinessId(): void
    {
        $businessId = (int) $this->selectedBusinessId;
        $currentId = (int) (Auth::user()?->current_business_id ?? 0);

        if ($businessId === 0 || $businessId === $currentId) {
            return;
        }

        $this->switchTo($businessId);
    }

    public function switchTo(int $businessId): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($businessId === (int) $user->current_business_id) {
            return;
        }

        $business = Business::query()->findOrFail($businessId);

        try {
            $user->switchBusiness($business);
        } catch (AuthorizationException) {
            $this->selectedBusinessId = (string) ($user->current_business_id ?? '');

            Flux::toast(__('general.business_switch_denied'), variant: 'danger');

            return;
        }

        Flux::toast(__('general.business_switched'));

        $this->redirect(route('user.dashboard'), navigate: false);
    }

    /**
     * @return Collection<int, Business>
     */
    #[Computed]
    public function businesses(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->cachedBusinesses();
    }

    #[Computed]
    public function currentBusiness(): ?Business
    {
        $id = Auth::user()?->current_business_id;

        if ($id === null) {
            return null;
        }

        return $this->businesses->firstWhere('id', $id);
    }

    #[Computed]
    public function membership(): ?BusinessUser
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->currentMembership();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function roleLabels(): array
    {
        $membership = $this->membership;

        if ($membership === null) {
            return [];
        }

        return array_map(
            fn (BusinessRole $role): string => $role->label(),
            $membership->roleEnums(),
        );
    }

    /**
     * @return list<array{label: string, icon: string, color: string, href: string}>
     */
    #[Computed]
    public function panels(): array
    {
        $membership = $this->membership;

        if ($membership === null) {
            return [];
        }

        $meta = [
            BusinessPanel::Accounting->value => ['icon' => 'calculator', 'color' => 'sky'],
            BusinessPanel::Warehouse->value => ['icon' => 'warehouse', 'color' => 'amber'],
            BusinessPanel::Sale->value => ['icon' => 'shopping-bag', 'color' => 'emerald'],
        ];

        return collect(BusinessPanel::cases())
            ->filter(fn (BusinessPanel $panel): bool => $membership->canAccessPanel($panel))
            ->map(fn (BusinessPanel $panel): array => [
                'label' => $panel->label(),
                'icon' => $meta[$panel->value]['icon'],
                'color' => $meta[$panel->value]['color'],
                'href' => route($panel->dashboardRoute()),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{open: int, waiting: int}
     */
    #[Computed]
    public function ticketSummary(): array
    {
        $stats = Ticket::query()
            ->where('user_id', Auth::id())
            ->selectRaw(
                'coalesce(sum(case when status != ? then 1 else 0 end), 0) as open_count, coalesce(sum(case when status = ? then 1 else 0 end), 0) as waiting_count',
                [TicketStatus::Closed->value, TicketStatus::WaitingUser->value],
            )
            ->first();

        return [
            'open' => (int) data_get($stats, 'open_count', 0),
            'waiting' => (int) data_get($stats, 'waiting_count', 0),
        ];
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function recentTickets(): Collection
    {
        return Ticket::query()
            ->where('user_id', Auth::id())
            ->with(['business:id,name'])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function formatDateTime(\DateTimeInterface $date): string
    {
        return Jalalian::fromDateTime($date)->format('Y/m/d H:i');
    }
};
?>

<x-slot name="title">{{ __('general.dashboard') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">
            {{ __('general.dashboard') }}
        </flux:heading>

        @if (auth()->user()->fullName())
            <flux:text class="mt-2 text-base">
                {{ auth()->user()->fullName() }}
            </flux:text>
        @endif

        <flux:text class="mt-1">
            {{ __('general.user_dashboard_hint') }}
        </flux:text>
    </div>

    @if ($this->currentBusiness)
        <flux:card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-2">
                    <flux:text>{{ __('general.current_business') }}</flux:text>
                    <flux:heading size="lg">{{ $this->currentBusiness->name }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @if ($this->currentBusiness->type)
                            <flux:badge size="sm" color="zinc">{{ $this->currentBusiness->type->label() }}</flux:badge>
                        @endif
                        @if ($this->currentBusiness->category)
                            <flux:badge size="sm" color="sky">{{ $this->currentBusiness->category->label() }}</flux:badge>
                        @endif
                        @foreach ($this->roleLabels as $label)
                            <flux:badge size="sm" color="teal" wire:key="role-{{ $label }}">{{ $label }}</flux:badge>
                        @endforeach
                    </div>
                </div>

                <div class="flex w-full flex-col gap-3 lg:max-w-sm">
                    @if ($this->businesses->count() > 1)
                        <flux:select
                            wire:model.live="selectedBusinessId"
                            variant="listbox"
                            searchable
                            placeholder="{{ __('general.switch_business') }}"
                        >
                            @foreach ($this->businesses as $business)
                                <flux:select.option :value="(string) $business->id" wire:key="switch-business-{{ $business->id }}">
                                    {{ $business->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <flux:button
                            size="sm"
                            variant="primary"
                            color="zinc"
                            icon="eye"
                            :href="route('user.businesses.view', $this->currentBusiness)"
                            wire:navigate
                        >
                            {{ __('general.view_business') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="primary"
                            color="teal"
                            icon="plus"
                            :href="route('user.businesses.create')"
                            wire:navigate
                        >
                            {{ __('general.create_business') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            @if ($this->panels !== [])
                <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:text class="mb-3">{{ __('general.quick_links') }}</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->panels as $panel)
                            <flux:button
                                size="sm"
                                variant="primary"
                                :color="$panel['color']"
                                :icon="$panel['icon']"
                                :href="$panel['href']"
                                wire:navigate
                                wire:key="panel-{{ $panel['href'] }}"
                            >
                                {{ $panel['label'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            @endif
        </flux:card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('user.businesses.index') }}" wire:navigate class="block min-w-0">
            <flux:card class="h-full transition hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex items-start justify-between gap-3">
                    <flux:text>{{ __('general.my_businesses') }}</flux:text>
                    <flux:badge size="sm" color="teal" icon="building" />
                </div>
                <flux:heading size="xl" class="mt-2 break-all text-2xl tabular-nums sm:text-3xl" dir="ltr">
                    {{ $this->businesses->count() }}
                </flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('user.support.tickets.index') }}" wire:navigate class="block min-w-0">
            <flux:card class="h-full transition hover:border-sky-300 dark:hover:border-sky-700">
                <div class="flex items-start justify-between gap-3">
                    <flux:text>{{ __('general.open_tickets') }}</flux:text>
                    <flux:badge size="sm" color="sky" icon="life-buoy" />
                </div>
                <flux:heading size="xl" class="mt-2 break-all text-2xl tabular-nums sm:text-3xl" dir="ltr">
                    {{ $this->ticketSummary['open'] }}
                </flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('user.support.tickets.index') }}" wire:navigate class="block min-w-0">
            <flux:card class="h-full transition hover:border-violet-300 dark:hover:border-violet-700">
                <div class="flex items-start justify-between gap-3">
                    <flux:text>{{ __('general.tickets_waiting_for_you') }}</flux:text>
                    <flux:badge size="sm" color="violet" icon="message-circle" />
                </div>
                <flux:heading size="xl" class="mt-2 break-all text-2xl tabular-nums sm:text-3xl" dir="ltr">
                    {{ $this->ticketSummary['waiting'] }}
                </flux:heading>
            </flux:card>
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.recent_tickets') }}</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        color="teal"
                        icon="plus"
                        :href="route('user.support.tickets.create')"
                        wire:navigate
                    >
                        {{ __('general.create_ticket') }}
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        :href="route('user.support.tickets.index')"
                        wire:navigate
                    >
                        {{ __('general.view_all') }}
                    </flux:button>
                </div>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('general.title') }}</flux:table.column>
                    <flux:table.column>{{ __('general.business') }}</flux:table.column>
                    <flux:table.column>{{ __('general.status') }}</flux:table.column>
                    <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentTickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell>{{ $ticket->title }}</flux:table.cell>
                            <flux:table.cell>{{ $ticket->business?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->status->badgeColor()">
                                    {{ $ticket->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatDateTime($ticket->created_at) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:tooltip content="{{ __('general.view') }}">
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        color="teal"
                                        icon="eye"
                                        icon:variant="outline"
                                        :href="route('user.support.tickets.view', $ticket)"
                                        wire:navigate
                                    />
                                </flux:tooltip>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <div class="py-6 text-center">
                                    <flux:text>{{ __('general.no_tickets') }}</flux:text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.my_businesses') }}</flux:heading>
                <flux:button
                    size="sm"
                    variant="ghost"
                    class="shrink-0"
                    :href="route('user.businesses.index')"
                    wire:navigate
                >
                    {{ __('general.view_all') }}
                </flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('general.name') }}</flux:table.column>
                    <flux:table.column>{{ __('general.business_type') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->businesses->take(6) as $business)
                        <flux:table.row :key="$business->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $business->name }}</span>
                                    @if (auth()->user()->current_business_id === $business->id)
                                        <flux:badge size="sm" color="teal">{{ __('general.active') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $business->type?->label() ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    @if ($this->businesses->count() > 1 && auth()->user()->current_business_id !== $business->id)
                                        <flux:tooltip content="{{ __('general.switch_business') }}">
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                color="teal"
                                                icon="repeat"
                                                icon:variant="outline"
                                                wire:click="switchTo({{ $business->id }})"
                                            />
                                        </flux:tooltip>
                                    @endif
                                    <flux:tooltip content="{{ __('general.view_business') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="zinc"
                                            icon="eye"
                                            icon:variant="outline"
                                            :href="route('user.businesses.view', $business)"
                                            wire:navigate
                                        />
                                    </flux:tooltip>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3">
                                {{ __('general.no_businesses') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</div>
