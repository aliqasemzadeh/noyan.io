<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', config('app.name', 'Noyan'));
        $this->migrator->add('general.site_short_name', 'Noyan');
        $this->migrator->add('general.site_description', '');
        $this->migrator->add('general.site_logo', null);
        $this->migrator->add('general.site_favicon', null);
        $this->migrator->add('general.locale', 'fa');
        $this->migrator->add('general.timezone', 'Asia/Tehran');

        $this->migrator->add('contact.mobile', null);
        $this->migrator->add('contact.phone', null);
        $this->migrator->add('contact.address', null);
        $this->migrator->add('contact.postal_code', null);
        $this->migrator->add('contact.fax', null);
        $this->migrator->add('contact.phone_numbers', []);
        $this->migrator->add('contact.support_email', null);

        $this->migrator->add('maintenance.is_maintenance_mode', false);
        $this->migrator->add('maintenance.secret_token', null);
        $this->migrator->add('maintenance.message', null);

        $this->migrator->add('security.banned_ips', []);
        $this->migrator->add('security.banned_mobiles', []);

        $this->migrator->add('social.telegram', null);
        $this->migrator->add('social.eitaa', null);
        $this->migrator->add('social.bale', null);
        $this->migrator->add('social.rubika', null);
        $this->migrator->add('social.soroush', null);
        $this->migrator->add('social.aparat', null);
        $this->migrator->add('social.instagram', null);
        $this->migrator->add('social.linkedin', null);
        $this->migrator->add('social.x_twitter', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.site_name');
        $this->migrator->deleteIfExists('general.site_short_name');
        $this->migrator->deleteIfExists('general.site_description');
        $this->migrator->deleteIfExists('general.site_logo');
        $this->migrator->deleteIfExists('general.site_favicon');
        $this->migrator->deleteIfExists('general.locale');
        $this->migrator->deleteIfExists('general.timezone');

        $this->migrator->deleteIfExists('contact.mobile');
        $this->migrator->deleteIfExists('contact.phone');
        $this->migrator->deleteIfExists('contact.address');
        $this->migrator->deleteIfExists('contact.postal_code');
        $this->migrator->deleteIfExists('contact.fax');
        $this->migrator->deleteIfExists('contact.phone_numbers');
        $this->migrator->deleteIfExists('contact.support_email');

        $this->migrator->deleteIfExists('maintenance.is_maintenance_mode');
        $this->migrator->deleteIfExists('maintenance.secret_token');
        $this->migrator->deleteIfExists('maintenance.message');

        $this->migrator->deleteIfExists('security.banned_ips');
        $this->migrator->deleteIfExists('security.banned_mobiles');

        $this->migrator->deleteIfExists('social.telegram');
        $this->migrator->deleteIfExists('social.eitaa');
        $this->migrator->deleteIfExists('social.bale');
        $this->migrator->deleteIfExists('social.rubika');
        $this->migrator->deleteIfExists('social.soroush');
        $this->migrator->deleteIfExists('social.aparat');
        $this->migrator->deleteIfExists('social.instagram');
        $this->migrator->deleteIfExists('social.linkedin');
        $this->migrator->deleteIfExists('social.x_twitter');
    }
};
