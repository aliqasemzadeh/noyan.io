<?php

use App\Actions\Business\AcceptBusinessInvitationAction;
use App\Models\BusinessInvitation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts::auth')]
class extends Component
{
    public string $token = '';

    public ?BusinessInvitation $invitation = null;

    public string $status = 'pending';

    public string $message = '';

    public function mount(string $token): mixed
    {
        $this->token = $token;
        $this->invitation = BusinessInvitation::query()
            ->with('business')
            ->where('token', $token)
            ->first();

        if ($this->invitation === null || $this->invitation->trashed()) {
            $this->status = 'invalid';
            $this->message = __('general.business_invitation_invalid');

            return null;
        }

        if ($this->invitation->isAccepted()) {
            $this->status = 'accepted';
            $this->message = __('general.business_invitation_already_accepted');

            return null;
        }

        if ($this->invitation->isExpired()) {
            $this->status = 'expired';
            $this->message = __('general.business_invitation_expired');

            return null;
        }

        session([
            'business_invitation_token' => $this->invitation->token,
        ]);

        $user = Auth::user();

        if ($user === null) {
            return $this->redirect(
                route('login', ['invitation' => $this->invitation->token]),
                navigate: true,
            );
        }

        return $this->acceptInvitation(app(AcceptBusinessInvitationAction::class));
    }

    public function acceptInvitation(AcceptBusinessInvitationAction $action): mixed
    {
        $user = Auth::user();

        if ($user === null || $this->invitation === null) {
            return $this->redirect(route('login', ['invitation' => $this->token]), navigate: true);
        }

        try {
            $action->handle($this->invitation->fresh() ?? $this->invitation, $user);
        } catch (ValidationException $exception) {
            $this->status = 'invalid';
            $this->message = collect($exception->errors())->flatten()->first()
                ?: __('general.business_invitation_invalid');

            session()->forget('business_invitation_token');

            return null;
        }

        session()->forget('business_invitation_token');

        Flux::toast(__('general.business_invitation_accepted'));

        return $this->redirect(route('user.dashboard'), navigate: true);
    }
};
?>

<x-slot name="title">{{ __('general.business_invitation') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6 text-center">
    <flux:heading size="xl">{{ __('general.business_invitation') }}</flux:heading>

    @if ($invitation?->business)
        <flux:text>{{ $invitation->business->name }}</flux:text>
    @endif

    @if ($status !== 'pending')
        <flux:callout variant="secondary" icon="info">
            {{ $message }}
        </flux:callout>

        <flux:button variant="primary" color="teal" :href="route('login')" wire:navigate class="w-full">
            {{ __('general.login') }}
        </flux:button>
    @else
        <flux:text>{{ __('general.business_invitation_processing') }}</flux:text>
    @endif
</div>
