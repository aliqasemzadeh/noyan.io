<?php

use App\Actions\Auth\LeaveImpersonationAction;
use App\Support\Impersonation;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public function leave(LeaveImpersonationAction $action): void
    {
        try {
            $action->handle();
        } catch (ValidationException $exception) {
            Flux::toast(
                collect($exception->errors())->flatten()->first() ?? __('general.impersonation_not_active'),
                variant: 'danger',
            );

            return;
        }

        Flux::toast(__('general.impersonation_left'));

        $this->redirect(route('system.users.index'), navigate: true);
    }
};
?>

@if (Impersonation::isImpersonating())
    <div class="mb-4">
        <flux:callout icon="triangle-alert" variant="warning" inline>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="sm">{{ __('general.impersonation_active_title') }}</flux:heading>
                    <flux:text>
                        {{ __('general.impersonation_active_message', [
                            'user' => auth()->user()?->fullName() ?? auth()->user()?->mobile,
                        ]) }}
                    </flux:text>
                </div>

                <flux:button
                    variant="primary"
                    color="orange"
                    icon="log-out"
                    wire:click="leave"
                    class="w-full sm:w-auto"
                >
                    {{ __('general.impersonation_leave') }}
                </flux:button>
            </div>
        </flux:callout>
    </div>
@endif
