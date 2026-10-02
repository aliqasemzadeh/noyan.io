<?php

use App\Livewire\Forms\UserForm;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public UserForm $form;

    public function save(): void
    {
        $user = $this->form->store();

        $this->dispatch('panels.administrator.user.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.user_created', ['mobile' => $user->mobile]));
    }
};
?>

<flux:modal name="user.create" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.create_user') }}</flux:heading>
    </div>

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