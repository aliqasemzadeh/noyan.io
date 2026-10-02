<?php

use App\Livewire\Forms\UserForm;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public UserForm $form;

    public ?User $user = null;

    #[On('panels.administrator.user.edit.assign-data')]
    public function assignData(User $user): void
    {
        $this->user = $user;
        $this->form->setModel($user);
        $this->resetValidation();

        Flux::modal('user.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();

        $this->dispatch('panels.administrator.user.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.user_updated'));
    }
};
?>

<flux:modal name="user.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_user') }}</flux:heading>
    </div>

    @if ($user)
        <flux:callout icon="box" variant="secondary" inline>
            {{ $user->mobile }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>{{ __('general.mobile') }}</flux:label>
            <flux:input
                wire:model="form.mobile"
                placeholder="{{ __('general.mobile_placeholder') }}"
                dir="ltr"
            />
            <flux:error name="form.mobile" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>