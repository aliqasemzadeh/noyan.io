<?php

use App\Livewire\Forms\Settings\MaintenanceSettingsForm;
use App\Settings\MaintenanceSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

new class extends Component
{
    public MaintenanceSettingsForm $form;

    public function mount(MaintenanceSettings $settings): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->setSettings($settings);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $settings = $this->form->store();

        if ($settings->is_maintenance_mode) {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }

            Artisan::call('down', array_filter([
                '--secret' => $settings->secret_token,
                '--render' => 'errors.maintenance',
            ]));
        } elseif (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        Flux::toast(__('general.settings_saved'));
    }
};
?>

<div class="mx-auto max-w-2xl space-y-6 py-4">
    <div class="flex items-start gap-3">
        <flux:icon.construction class="mt-0.5 size-5 text-amber-600 dark:text-amber-400" />
        <div>
            <flux:heading size="lg">{{ __('general.maintenance_settings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.maintenance_settings_hint') }}</flux:text>
        </div>
    </div>

    <flux:callout icon="triangle-alert" variant="warning">
        <flux:callout.text>{{ __('general.maintenance_warning') }}</flux:callout.text>
    </flux:callout>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:switch
                    wire:model.live="form.is_maintenance_mode"
                    label="{{ __('general.maintenance_mode') }}"
                    description="{{ __('general.maintenance_mode_hint') }}"
                />
                <flux:error name="form.is_maintenance_mode" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.maintenance_secret') }}</flux:label>
                <flux:input
                    wire:model="form.secret_token"
                    placeholder="my-secret-token"
                    dir="ltr"
                />
                <flux:description>{{ __('general.maintenance_secret_hint') }}</flux:description>
                <flux:error name="form.secret_token" />
            </flux:field>

            @if ($form->is_maintenance_mode && filled($form->secret_token))
                <flux:callout icon="key" variant="secondary">
                    <flux:callout.heading>{{ __('general.maintenance_bypass_url') }}</flux:callout.heading>
                    <flux:callout.text>
                        <span dir="ltr" class="break-all">{{ url('/'.$form->secret_token) }}</span>
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('general.maintenance_message') }}</flux:label>
                <flux:textarea wire:model="form.message" rows="4" />
                <flux:description>{{ __('general.maintenance_message_hint') }}</flux:description>
                <flux:error name="form.message" />
            </flux:field>

            <flux:button type="submit" variant="primary" color="amber" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
