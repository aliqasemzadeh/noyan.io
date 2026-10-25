<?php

namespace App\Livewire\Forms\Settings;

use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class GeneralSettingsForm extends Form
{
    public string $site_name = '';

    public string $site_short_name = '';

    public string $site_description = '';

    public string $locale = 'fa';

    public string $timezone = 'Asia/Tehran';

    public TemporaryUploadedFile|string|null $site_logo = null;

    public TemporaryUploadedFile|string|null $site_favicon = null;

    public ?string $current_logo = null;

    public ?string $current_favicon = null;

    public function setSettings(GeneralSettings $settings): void
    {
        $this->site_name = $settings->site_name;
        $this->site_short_name = $settings->site_short_name;
        $this->site_description = $settings->site_description;
        $this->locale = $settings->locale;
        $this->timezone = $settings->timezone;
        $this->current_logo = $settings->site_logo;
        $this->current_favicon = $settings->site_favicon;
        $this->site_logo = null;
        $this->site_favicon = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'site_short_name' => ['required', 'string', 'max:10'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'locale' => ['required', 'string', Rule::in(['fa', 'en'])],
            'timezone' => ['required', 'string', 'timezone:all'],
            'site_logo' => ['nullable', 'image', 'max:2048'],
            'site_favicon' => ['nullable', 'file', 'mimes:png,ico,svg,jpg,jpeg', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'site_name' => __('general.site_name'),
            'site_short_name' => __('general.site_short_name'),
            'site_description' => __('general.site_description'),
            'locale' => __('general.locale'),
            'timezone' => __('general.timezone'),
            'site_logo' => __('general.site_logo'),
            'site_favicon' => __('general.site_favicon'),
        ];
    }

    public function store(): void
    {
        $this->validate();

        $settings = app(GeneralSettings::class);

        $settings->site_name = $this->site_name;
        $settings->site_short_name = $this->site_short_name;
        $settings->site_description = $this->site_description;
        $settings->locale = $this->locale;
        $settings->timezone = $this->timezone;

        if ($this->site_logo instanceof TemporaryUploadedFile) {
            if ($settings->site_logo) {
                Storage::disk('public')->delete($settings->site_logo);
            }

            $settings->site_logo = $this->site_logo->store('settings', 'public');
        }

        if ($this->site_favicon instanceof TemporaryUploadedFile) {
            if ($settings->site_favicon) {
                Storage::disk('public')->delete($settings->site_favicon);
            }

            $settings->site_favicon = $this->site_favicon->store('settings', 'public');
        }

        $settings->save();

        $this->setSettings($settings);
    }

    public function removeLogo(): void
    {
        $settings = app(GeneralSettings::class);

        if ($settings->site_logo) {
            Storage::disk('public')->delete($settings->site_logo);
        }

        $settings->site_logo = null;
        $settings->save();

        $this->current_logo = null;
        $this->site_logo = null;
    }

    public function removeFavicon(): void
    {
        $settings = app(GeneralSettings::class);

        if ($settings->site_favicon) {
            Storage::disk('public')->delete($settings->site_favicon);
        }

        $settings->site_favicon = null;
        $settings->save();

        $this->current_favicon = null;
        $this->site_favicon = null;
    }
}
