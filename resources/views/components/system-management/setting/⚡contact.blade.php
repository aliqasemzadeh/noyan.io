<?php

use App\Livewire\Forms\Settings\ContactSettingsForm;
use App\Settings\ContactSettings;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public ContactSettingsForm $form;

    public string $newPhoneNumber = '';

    public function mount(ContactSettings $settings): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->setSettings($settings);
    }

    public function addPhoneNumber(): void
    {
        $this->resetErrorBag('newPhoneNumber');

        $value = trim($this->newPhoneNumber);

        if ($value === '') {
            return;
        }

        if (in_array($value, $this->form->phone_numbers, true)) {
            $this->addError('newPhoneNumber', __('general.duplicate_item'));

            return;
        }

        $this->form->phone_numbers[] = $value;
        $this->newPhoneNumber = '';
    }

    public function removePhoneNumber(int $index): void
    {
        unset($this->form->phone_numbers[$index]);
        $this->form->phone_numbers = array_values($this->form->phone_numbers);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->store();

        Flux::toast(__('general.settings_saved'));
    }
};
?>

<div class="mx-auto max-w-2xl space-y-6 py-4">
    <div class="flex items-start gap-3">
        <flux:icon.phone class="mt-0.5 size-5 text-blue-600 dark:text-blue-400" />
        <div>
            <flux:heading size="lg">{{ __('general.contact_settings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.contact_settings_hint') }}</flux:text>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('general.mobile') }}</flux:label>
                    <flux:input
                        wire:model="form.mobile"
                        placeholder="09121234567"
                        dir="ltr"
                    />
                    <flux:error name="form.mobile" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.phone') }}</flux:label>
                    <flux:input
                        wire:model="form.phone"
                        placeholder="021-12345678"
                        dir="ltr"
                    />
                    <flux:description>{{ __('general.phone_hint') }}</flux:description>
                    <flux:error name="form.phone" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('general.address') }}</flux:label>
                <flux:textarea wire:model="form.address" rows="3" />
                <flux:description>{{ __('general.contact_address_hint') }}</flux:description>
                <flux:error name="form.address" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('general.postal_code') }}</flux:label>
                    <flux:input
                        wire:model="form.postal_code"
                        placeholder="1234567890"
                        dir="ltr"
                    />
                    <flux:description>{{ __('general.postal_code_hint') }}</flux:description>
                    <flux:error name="form.postal_code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.fax') }}</flux:label>
                    <flux:input
                        wire:model="form.fax"
                        placeholder="021-12345678"
                        dir="ltr"
                    />
                    <flux:description>{{ __('general.fax_hint') }}</flux:description>
                    <flux:error name="form.fax" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('general.support_email') }}</flux:label>
                <flux:input
                    type="email"
                    wire:model="form.support_email"
                    placeholder="support@example.com"
                    dir="ltr"
                />
                <flux:description>{{ __('general.support_email_hint') }}</flux:description>
                <flux:error name="form.support_email" />
            </flux:field>

            <div class="space-y-3">
                <flux:field>
                    <flux:label>{{ __('general.phone_numbers') }}</flux:label>
                    <flux:description>{{ __('general.phone_numbers_hint') }}</flux:description>
                    <flux:input.group>
                        <flux:input
                            wire:model="newPhoneNumber"
                            placeholder="021-12345678"
                            dir="ltr"
                            wire:keydown.enter.prevent="addPhoneNumber"
                        />
                        <flux:button type="button" wire:click="addPhoneNumber" color="blue">
                            {{ __('general.add_item') }}
                        </flux:button>
                    </flux:input.group>
                    <flux:error name="newPhoneNumber" />
                </flux:field>

                <div class="flex flex-wrap gap-2">
                    @forelse ($form->phone_numbers as $index => $phone)
                        <flux:badge color="blue" size="sm" wire:key="phone-{{ $index }}">
                            <span dir="ltr">{{ $phone }}</span>
                            <flux:badge.close wire:click="removePhoneNumber({{ $index }})" />
                        </flux:badge>
                    @empty
                        <flux:text class="text-sm">{{ __('general.no_items_added') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <flux:button type="submit" variant="primary" color="teal" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
