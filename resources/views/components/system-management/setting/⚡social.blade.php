<?php

use App\Livewire\Forms\Settings\SocialSettingsForm;
use App\Settings\SocialSettings;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public SocialSettingsForm $form;

    public function mount(SocialSettings $settings): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->setSettings($settings);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->store();

        Flux::toast(__('general.settings_saved'));
    }
};
?>

@php
    $networks = [
        'telegram' => ['icon' => 'send', 'placeholder' => 'https://t.me/username'],
        'eitaa' => ['icon' => 'message-circle', 'placeholder' => 'https://eitaa.com/username'],
        'bale' => ['icon' => 'message-square', 'placeholder' => 'https://ble.ir/username'],
        'rubika' => ['icon' => 'smartphone', 'placeholder' => 'https://rubika.ir/username'],
        'soroush' => ['icon' => 'messages-square', 'placeholder' => 'https://splus.ir/username'],
        'aparat' => ['icon' => 'video', 'placeholder' => 'https://aparat.com/username'],
        'instagram' => ['icon' => 'camera', 'placeholder' => 'https://instagram.com/username'],
        'linkedin' => ['icon' => 'briefcase', 'placeholder' => 'https://linkedin.com/company/name'],
        'x_twitter' => ['icon' => 'at-sign', 'placeholder' => 'https://x.com/username'],
    ];
@endphp

<div class="mx-auto max-w-2xl space-y-6 py-4">
    <div class="flex items-start gap-3">
        <flux:icon.share-2 class="mt-0.5 size-5 text-violet-600 dark:text-violet-400" />
        <div>
            <flux:heading size="lg">{{ __('general.social_settings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.social_settings_hint') }}</flux:text>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            @foreach ($networks as $network => $meta)
                <flux:field wire:key="social-{{ $network }}">
                    <flux:label>{{ __('general.'.$network) }}</flux:label>
                    <flux:input
                        wire:model="form.{{ $network }}"
                        icon="{{ $meta['icon'] }}"
                        placeholder="{{ $meta['placeholder'] }}"
                        dir="ltr"
                    />
                    <flux:description>
                        {{ __('general.social_link_hint', ['network' => __('general.'.$network)]) }}
                    </flux:description>
                    <flux:error name="form.{{ $network }}" />
                </flux:field>
            @endforeach

            <flux:button type="submit" variant="primary" color="violet" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
