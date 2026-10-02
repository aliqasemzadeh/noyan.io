<?php

use App\Models\Business;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?Business $business = null;

    public string $confirmationName = '';

    #[On('panels.administrator.business.delete.assign-data')]
    public function assignData(Business $business): void
    {
        $this->business = $business;
        $this->confirmationName = '';
        $this->resetValidation();

        Flux::modal('business.delete')->show();
    }

    public function delete(): void
    {
        if ($this->business === null) {
            return;
        }

        $this->validate([
            'confirmationName' => [
                'required',
                'string',
                Rule::in([$this->business->name]),
            ],
        ], [
            'confirmationName.in' => __('general.business_name_confirmation_mismatch'),
            'confirmationName.required' => __('general.type_business_name_to_confirm'),
        ], [
            'confirmationName' => __('general.name'),
        ]);

        $memberIds = $this->business->users()->pluck('users.id');

        $this->business->delete();

        User::query()->whereIn('id', $memberIds)->each(fn (User $user) => $user->forgetBusinessesCache());

        $this->reset('business', 'confirmationName');

        $this->dispatch('panels.administrator.business.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.business_deleted'));
    }
};
?>

<flux:modal name="business.delete" class="min-w-[22rem]">
    <form wire:submit="delete" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('general.delete_confirmation') }}</flux:heading>

            <flux:text class="mt-2">
                {{ __('general.delete_business_warning') }}<br>
                {{ __('general.action_cannot_be_reversed') }}
            </flux:text>
        </div>

        @if ($business)
            <flux:callout icon="box" variant="secondary" inline>
                {{ $business->name }}
            </flux:callout>

            <flux:field>
                <flux:label>{{ __('general.type_business_name_to_confirm') }}</flux:label>
                <flux:input
                    wire:model="confirmationName"
                    placeholder="{{ $business->name }}"
                />
                <flux:error name="confirmationName" />
            </flux:field>
        @endif

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('general.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="danger">{{ __('general.delete') }}</flux:button>
        </div>
    </form>
</flux:modal>
