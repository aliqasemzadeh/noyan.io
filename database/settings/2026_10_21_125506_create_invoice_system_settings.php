<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoice.invoice_global_footer', '');
        $this->migrator->add('invoice.sms_unit_cost', '1000');
    }
};
