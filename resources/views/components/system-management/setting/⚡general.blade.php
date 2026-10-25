<?php

use App\Livewire\Forms\Settings\GeneralSettingsForm;
use App\Settings\GeneralSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public GeneralSettingsForm $form;

    public function mount(GeneralSettings $settings): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->setSettings($settings);
    }

    #[Computed]
    public function timezones(): array
    {
        return timezone_identifiers_list();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $previousLocale = app()->getLocale();

        $this->form->store();

        $settings = app(GeneralSettings::class);

        config([
            'app.name' => $settings->site_name,
            'app.locale' => $settings->locale,
            'app.timezone' => $settings->timezone,
        ]);

        app()->setLocale($settings->locale);
        date_default_timezone_set($settings->timezone);
        session(['locale' => $settings->locale]);

        Flux::toast(__('general.settings_saved'));

        if ($previousLocale !== $settings->locale) {
            $this->redirect(request()->header('Referer') ?: url()->current(), navigate: false);
        }
    }

    public function removeLogo(): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->removeLogo();

        Flux::toast(__('general.settings_saved'));
    }

    public function removeFavicon(): void
    {
        abort_unless(auth()->user()?->can('setting_view'), 403);

        $this->form->removeFavicon();

        Flux::toast(__('general.settings_saved'));
    }
};
?>

<div class="mx-auto max-w-2xl space-y-6 py-4">
    <div class="flex items-start gap-3">
        <flux:icon.settings class="mt-0.5 size-5 text-teal-600 dark:text-teal-400" />
        <div>
            <flux:heading size="lg">{{ __('general.general_settings') }}</flux:heading>
            <flux:text class="mt-1">{{ __('general.general_settings_hint') }}</flux:text>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('general.site_name') }}</flux:label>
                <flux:input wire:model="form.site_name" />
                <flux:error name="form.site_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.site_short_name') }}</flux:label>
                <flux:input wire:model="form.site_short_name" />
                <flux:description>{{ __('general.site_short_name_hint') }}</flux:description>
                <flux:error name="form.site_short_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.site_description') }}</flux:label>
                <flux:textarea wire:model="form.site_description" rows="3" />
                <flux:error name="form.site_description" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('general.locale') }}</flux:label>
                    <flux:select wire:model="form.locale" searchable>
                        <flux:select.option value="fa">{{ __('general.locale_fa') }}</flux:select.option>
                        <flux:select.option value="en">{{ __('general.locale_en') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="form.locale" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.timezone') }}</flux:label>
                    <flux:select wire:model="form.timezone" searchable variant="listbox">
                        @foreach ($this->timezones as $timezone)
                            <flux:select.option value="{{ $timezone }}" wire:key="timezone-{{ $timezone }}">
                                {{ $timezone }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.timezone" />
                </flux:field>
            </div>

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('general.site_logo') }}</flux:heading>
                <flux:text>{{ __('general.site_logo_hint') }}</flux:text>

                @if ($form->current_logo)
                    <div class="flex items-center gap-3">
                        <img
                            src="{{ Storage::disk('public')->url($form->current_logo) }}"
                            alt="{{ __('general.site_logo') }}"
                            class="h-16 w-auto rounded-lg border border-zinc-200 dark:border-zinc-700"
                        />
                        <flux:button
                            type="button"
                            size="sm"
                            variant="danger"
                            wire:click="removeLogo"
                            wire:confirm="{{ __('general.are_you_sure') }}"
                        >
                            {{ __('general.remove') }}
                        </flux:button>
                    </div>
                @endif

                <flux:file-upload wire:model="form.site_logo">
                    <flux:file-upload.dropzone
                        inline
                        heading="{{ __('general.upload_file_hint') }}"
                        text="PNG, JPG, SVG (max 2MB)"
                    />
                </flux:file-upload>
                <flux:error name="form.site_logo" />
            </div>

            <div class="space-y-3">
                <flux:heading size="sm">{{ __('general.site_favicon') }}</flux:heading>
                <flux:text>{{ __('general.site_favicon_hint') }}</flux:text>

                @if ($form->current_favicon)
                    <div class="flex items-center gap-3">
                        <img
                            src="{{ Storage::disk('public')->url($form->current_favicon) }}"
                            alt="{{ __('general.site_favicon') }}"
                            class="size-10 rounded border border-zinc-200 dark:border-zinc-700"
                        />
                        <flux:button
                            type="button"
                            size="sm"
                            variant="danger"
                            wire:click="removeFavicon"
                            wire:confirm="{{ __('general.are_you_sure') }}"
                        >
                            {{ __('general.remove') }}
                        </flux:button>
                    </div>
                @endif

                <flux:file-upload wire:model="form.site_favicon">
                    <flux:file-upload.dropzone
                        inline
                        heading="{{ __('general.upload_file_hint') }}"
                        text="PNG, ICO, SVG (max 1MB)"
                    />
                </flux:file-upload>
                <flux:error name="form.site_favicon" />
            </div>

            <flux:button type="submit" variant="primary" color="teal" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>
