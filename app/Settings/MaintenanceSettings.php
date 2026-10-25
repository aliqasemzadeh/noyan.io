<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MaintenanceSettings extends Settings
{
    public bool $is_maintenance_mode;

    public ?string $secret_token;

    public ?string $message;

    public static function group(): string
    {
        return 'maintenance';
    }
}
