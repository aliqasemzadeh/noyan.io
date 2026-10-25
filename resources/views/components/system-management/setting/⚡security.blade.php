<?php

use App\Livewire\Forms\Settings\SecuritySettingsForm;
use App\Settings\SecuritySettings;
use App\Support\Money;
use Flux\Flux;
use Livewire\Component;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

new class extends Component
{
    public SecuritySettingsForm $form;

    public string $newBannedIp = '';

    public string $newBannedMobile = '';

    public function mount(SecuritySettings $settings): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->setSettings($settings);
    }

    public function addBannedIp(): void
    {
        $this->resetErrorBag('newBannedIp');

        $value = trim($this->newBannedIp);

        if ($value === '') {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            $this->addError('newBannedIp', __('general.invalid_ip'));

            return;
        }

        if (in_array($value, $this->form->banned_ips, true)) {
            $this->addError('newBannedIp', __('general.duplicate_item'));

            return;
        }

        $this->form->banned_ips[] = $value;
        $this->newBannedIp = '';
    }

    public function removeBannedIp(int $index): void
    {
        unset($this->form->banned_ips[$index]);
        $this->form->banned_ips = array_values($this->form->banned_ips);
    }

    public function addBannedMobile(): void
    {
        $this->resetErrorBag('newBannedMobile');

        $value = Money::normalize(trim($this->newBannedMobile));

        if ($value === '') {
            return;
        }

        $validator = validator(
            ['mobile' => $value],
            ['mobile' => ['required', 'string', new IranianMobile(format: 'zero')]],
            attributes: ['mobile' => __('general.mobile')],
        );

        if ($validator->fails()) {
            $this->addError('newBannedMobile', $validator->errors()->first('mobile'));

            return;
        }

        if (in_array($value, $this->form->banned_mobiles, true)) {
            $this->addError('newBannedMobile', __('general.duplicate_item'));

            return;
        }

        $this->form->banned_mobiles[] = $value;
        $this->newBannedMobile = '';
    }

    public function removeBannedMobile(int $index): void
    {
        unset($this->form->banned_mobiles[$index]);
        $this->form->banned_mobiles = array_values($this->form->banned_mobiles);
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
        <flux:icon.shield class="mt-0.5 size-5 text-red-600 dark:text-red-400" />
        <div>
            <flux:heading size="lg">{{ __('general.security_settings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.security_settings_hint') }}</flux:text>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-8">
            <div class="space-y-3">
                <flux:field>
                    <flux:label>{{ __('general.banned_ips') }}</flux:label>
                    <flux:description>{{ __('general.banned_ips_hint') }}</flux:description>
                    <div dir="ltr">
                        <flux:input.group>
                            <flux:input
                                wire:model="newBannedIp"
                                placeholder="1.2.3.4"
                                wire:keydown.enter.prevent="addBannedIp"
                            />
                            <flux:button type="button" wire:click="addBannedIp" color="red">
                                {{ __('general.add_item') }}
                            </flux:button>
                        </flux:input.group>
                    </div>
                    <flux:error name="newBannedIp" />
                </flux:field>

                <div class="flex flex-wrap gap-2">
                    @forelse ($form->banned_ips as $index => $ip)
                        <flux:badge color="red" size="sm" wire:key="banned-ip-{{ $index }}">
                            <span dir="ltr">{{ $ip }}</span>
                            <flux:badge.close wire:click="removeBannedIp({{ $index }})" />
                        </flux:badge>
                    @empty
                        <flux:text class="text-sm">{{ __('general.no_items_added') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="space-y-3">
                <flux:field>
                    <flux:label>{{ __('general.banned_mobiles') }}</flux:label>
                    <flux:description>{{ __('general.banned_mobiles_hint') }}</flux:description>
                    <div dir="ltr">
                        <flux:input.group>
                            <flux:input
                                wire:model="newBannedMobile"
                                placeholder="09121234567"
                                wire:keydown.enter.prevent="addBannedMobile"
                            />
                            <flux:button type="button" wire:click="addBannedMobile" color="red">
                                {{ __('general.add_item') }}
                            </flux:button>
                        </flux:input.group>
                    </div>
                    <flux:error name="newBannedMobile" />
                </flux:field>

                <div class="flex flex-wrap gap-2">
                    @forelse ($form->banned_mobiles as $index => $mobile)
                        <flux:badge color="rose" size="sm" wire:key="banned-mobile-{{ $index }}">
                            <span dir="ltr">{{ $mobile }}</span>
                            <flux:badge.close wire:click="removeBannedMobile({{ $index }})" />
                        </flux:badge>
                    @empty
                        <flux:text class="text-sm">{{ __('general.no_items_added') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <flux:button type="submit" variant="primary" color="red" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
