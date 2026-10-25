<?php

namespace App\Livewire\Forms\Settings;

use App\Settings\ContactSettings;
use Livewire\Form;
use Sadegh19b\LaravelPersianValidation\Rules\IranianMobile;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPhone;
use Sadegh19b\LaravelPersianValidation\Rules\IranianPostalCode;

class ContactSettingsForm extends Form
{
    public ?string $mobile = null;

    public ?string $phone = null;

    public ?string $address = null;

    public ?string $postal_code = null;

    public ?string $fax = null;

    /** @var array<int, string> */
    public array $phone_numbers = [];

    public ?string $support_email = null;

    public function setSettings(ContactSettings $settings): void
    {
        $this->mobile = $settings->mobile;
        $this->phone = $settings->phone;
        $this->address = $settings->address;
        $this->postal_code = $settings->postal_code;
        $this->fax = $settings->fax;
        $this->phone_numbers = $settings->phone_numbers;
        $this->support_email = $settings->support_email;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mobile' => ['nullable', 'string', new IranianMobile(format: 'zero')],
            'phone' => ['nullable', 'string', new IranianPhone(withAreaCode: true, areaCodeSeparator: '-')],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', new IranianPostalCode],
            'fax' => ['nullable', 'string', 'max:20'],
            'phone_numbers' => ['array'],
            'phone_numbers.*' => ['string', 'max:20'],
            'support_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'mobile' => __('general.mobile'),
            'phone' => __('general.phone'),
            'address' => __('general.address'),
            'postal_code' => __('general.postal_code'),
            'fax' => __('general.fax'),
            'phone_numbers' => __('general.phone_numbers'),
            'support_email' => __('general.support_email'),
        ];
    }

    public function store(): void
    {
        $this->validate();

        $settings = app(ContactSettings::class);

        $settings->mobile = $this->mobile ?: null;
        $settings->phone = $this->phone ?: null;
        $settings->address = $this->address ?: null;
        $settings->postal_code = $this->postal_code ?: null;
        $settings->fax = $this->fax ?: null;
        $settings->phone_numbers = array_values($this->phone_numbers);
        $settings->support_email = $this->support_email ?: null;

        $settings->save();
    }
}
