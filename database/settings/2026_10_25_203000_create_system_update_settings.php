<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system_update.installed_version', '');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('system_update.installed_version');
    }
};
