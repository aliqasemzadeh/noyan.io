<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SyncAdministratorPermissions;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(SyncAdministratorPermissions::class)->handle();

        $seedUser = User::query()->firstOrCreate([
            'mobile' => '09123456789',
        ]);

        if (! $seedUser->hasRole('administrator')) {
            $seedUser->assignRole('administrator');
        }
    }
}
