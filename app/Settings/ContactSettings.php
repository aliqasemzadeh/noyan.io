<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContactSettings extends Settings
{
    public ?string $mobile;

    public ?string $phone;

    public ?string $address;

    public ?string $postal_code;

    public ?string $fax;

    /** @var array<int, string> */
    public array $phone_numbers;

    public ?string $support_email;

    public static function group(): string
    {
        return 'contact';
    }
}
