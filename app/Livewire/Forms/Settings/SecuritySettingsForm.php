<?php

namespace App\Livewire\Forms\Settings;

use App\Settings\SecuritySettings;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;

class SecuritySettingsForm extends Form
{
    /** @var array<int, string> */
    public array $banned_ips = [];

    /** @var array<int, string> */
    public array $banned_mobiles = [];

    public function setSettings(SecuritySettings $settings): void
    {
        $this->banned_ips = $settings->banned_ips;
        $this->banned_mobiles = $settings->banned_mobiles;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'banned_ips' => ['array'],
            'banned_ips.*' => ['ip'],
            'banned_mobiles' => ['array'],
            'banned_mobiles.*' => ['string', new IranianMobile(format: 'zero')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'banned_ips' => __('general.banned_ips'),
            'banned_mobiles' => __('general.banned_mobiles'),
        ];
    }

    public function store(): void
    {
        $this->validate();

        $settings = app(SecuritySettings::class);

        $settings->banned_ips = array_values($this->banned_ips);
        $settings->banned_mobiles = array_values($this->banned_mobiles);

        $settings->save();
    }
}
