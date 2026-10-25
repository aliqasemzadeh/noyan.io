<?php

namespace App\Livewire\Forms\Settings;

use App\Settings\SocialSettings;
use Livewire\Form;

class SocialSettingsForm extends Form
{
    public ?string $telegram = null;

    public ?string $eitaa = null;

    public ?string $bale = null;

    public ?string $rubika = null;

    public ?string $soroush = null;

    public ?string $aparat = null;

    public ?string $instagram = null;

    public ?string $linkedin = null;

    public ?string $x_twitter = null;

    public function setSettings(SocialSettings $settings): void
    {
        $this->telegram = $settings->telegram;
        $this->eitaa = $settings->eitaa;
        $this->bale = $settings->bale;
        $this->rubika = $settings->rubika;
        $this->soroush = $settings->soroush;
        $this->aparat = $settings->aparat;
        $this->instagram = $settings->instagram;
        $this->linkedin = $settings->linkedin;
        $this->x_twitter = $settings->x_twitter;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'telegram' => ['nullable', 'url', 'max:255'],
            'eitaa' => ['nullable', 'url', 'max:255'],
            'bale' => ['nullable', 'url', 'max:255'],
            'rubika' => ['nullable', 'url', 'max:255'],
            'soroush' => ['nullable', 'url', 'max:255'],
            'aparat' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'x_twitter' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'telegram' => __('general.telegram'),
            'eitaa' => __('general.eitaa'),
            'bale' => __('general.bale'),
            'rubika' => __('general.rubika'),
            'soroush' => __('general.soroush'),
            'aparat' => __('general.aparat'),
            'instagram' => __('general.instagram'),
            'linkedin' => __('general.linkedin'),
            'x_twitter' => __('general.x_twitter'),
        ];
    }

    public function store(): void
    {
        $this->validate();

        $settings = app(SocialSettings::class);

        $settings->telegram = $this->telegram ?: null;
        $settings->eitaa = $this->eitaa ?: null;
        $settings->bale = $this->bale ?: null;
        $settings->rubika = $this->rubika ?: null;
        $settings->soroush = $this->soroush ?: null;
        $settings->aparat = $this->aparat ?: null;
        $settings->instagram = $this->instagram ?: null;
        $settings->linkedin = $this->linkedin ?: null;
        $settings->x_twitter = $this->x_twitter ?: null;

        $settings->save();
    }
}
