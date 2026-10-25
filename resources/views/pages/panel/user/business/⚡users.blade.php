<?php

use App\Actions\Business\CancelBusinessInvitationAction;
use App\Enums\Business\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Morilog\Jalali\Jalalian;

new class extends Component
{
    use WithPagination;

    public Business $business;

    public string $search = '';

    public function mount(Business $business): void
    {
        abort_unless(Auth::user()?->owns($business), 403);

        $this->business = $business;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[On('panels.user.business.users.table')]
    public function refreshTable(): void
    {
        unset($this->memberships, $this->pendingInvitations);
    }

    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return BusinessUser::query()
            ->with('user')
            ->where('business_id', $this->business->id)
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';

                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('mobile', 'like', $search);
                });
            })
            ->latest()
            ->paginate(config('general.per_page', 15));
    }

    /**
     * @return Collection<int, BusinessInvitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return BusinessInvitation::query()
            ->pending()
            ->where('business_id', $this->business->id)
            ->when($this->search !== '', function ($query): void {
                $query->where('mobile', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->get();
    }

    public function cancelInvitation(int $invitationId, CancelBusinessInvitationAction $action): void
    {
        abort_unless(Auth::user()?->canManageBusinessUsers($this->business), 403);

        $invitation = BusinessInvitation::query()
            ->where('business_id', $this->business->id)
            ->whereKey($invitationId)
            ->firstOrFail();

        try {
            $action->handle($invitation);
        } catch (ValidationException $exception) {
            Flux::toast(
                collect($exception->errors())->flatten()->first() ?: __('general.business_invitation_invalid'),
                variant: 'danger',
            );

            return;
        }

        unset($this->pendingInvitations);

        Flux::toast(__('general.business_invitation_cancelled'));
    }

    public function formatCreatedAt(BusinessUser $membership): string
    {
        return Jalalian::fromDateTime($membership->created_at)->format('Y/m/d H:i');
    }

    public function formatExpiresAt(BusinessInvitation $invitation): string
    {
        return Jalalian::fromDateTime($invitation->expires_at)->format('Y/m/d H:i');
    }

    /**
     * @return list<string>
     */
    public function roleLabels(BusinessUser $membership): array
    {
        return array_map(
            fn (BusinessRole $role): string => $role->label(),
            $membership->roleEnums(),
        );
    }

    /**
     * @return list<string>
     */
    public function invitationRoleLabels(BusinessInvitation $invitation): array
    {
        return array_map(
            fn (BusinessRole $role): string => $role->label(),
            $invitation->roleEnums(),
        );
    }
};
?>

<x-slot name="title">{{ __('general.business_users') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.index')" wire:navigate>
                {{ __('general.my_businesses') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.businesses.view', $business)" wire:navigate>
                {{ $business->name }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.business_users') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">
                    {{ __('general.business_users') }}
                </flux:heading>
                <flux:text class="mt-2">{{ $business->name }}</flux:text>
            </div>

            <flux:button
                variant="primary"
                color="teal"
                icon="plus"
                wire:click="$dispatch('panels.user.business.create-user.assign-data', { business: {{ $business->id }} })"
            >
                {{ __('general.create_business_user') }}
            </flux:button>
        </div>
    </div>

    <flux:card>
        <div class="mb-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="search"
                placeholder="{{ __('general.search') }}..."
                clearable
            />
        </div>

        <flux:table :paginate="$this->memberships">
            <flux:table.columns>
                <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                <flux:table.column>{{ __('general.role') }}</flux:table.column>
                <flux:table.column>{{ __('general.created_at') }}</flux:table.column>
                <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->memberships as $membership)
                    <flux:table.row :key="$membership->id">
                        <flux:table.cell>
                            <span dir="ltr">{{ $membership->user?->mobile }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($this->roleLabels($membership) as $roleLabel)
                                    <flux:badge size="sm" color="zinc">{{ $roleLabel }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->formatCreatedAt($membership) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if (! $membership->isOwner())
                                <div class="flex justify-end gap-2">
                                    <flux:tooltip content="{{ __('general.edit') }}">
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="blue"
                                            icon="pencil"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.user.business.edit-user.assign-data', { membership: {{ $membership->id }} })"
                                        />
                                    </flux:tooltip>
                                    <flux:tooltip content="{{ __('general.delete') }}">
                                        <flux:button
                                            size="xs"
                                            variant="danger"
                                            icon="trash"
                                            icon:variant="outline"
                                            wire:click="$dispatch('panels.user.business.remove-user.assign-data', { membership: {{ $membership->id }} })"
                                        />
                                    </flux:tooltip>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            {{ __('general.no_business_users') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($this->pendingInvitations->isNotEmpty())
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('general.pending_business_invitations') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('general.mobile') }}</flux:table.column>
                    <flux:table.column>{{ __('general.role') }}</flux:table.column>
                    <flux:table.column>{{ __('general.expires_at') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('general.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->pendingInvitations as $invitation)
                        <flux:table.row :key="'invite-'.$invitation->id">
                            <flux:table.cell>
                                <span dir="ltr">{{ $invitation->mobile }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($this->invitationRoleLabels($invitation) as $roleLabel)
                                        <flux:badge size="sm" color="amber">{{ $roleLabel }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $this->formatExpiresAt($invitation) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:tooltip content="{{ __('general.cancel_invitation') }}">
                                    <flux:button
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                        icon:variant="outline"
                                        wire:click="cancelInvitation({{ $invitation->id }})"
                                        wire:confirm="{{ __('general.business_invitation_cancel_confirm') }}"
                                    />
                                </flux:tooltip>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <livewire:business.create-user :key="'business-create-user-'.$business->id" />
    <livewire:business.edit-user :key="'business-edit-user-'.$business->id" />
    <livewire:business.remove-user :key="'business-remove-user-'.$business->id" />
</div>
