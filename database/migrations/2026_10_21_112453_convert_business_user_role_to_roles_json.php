<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('user_id');
        });

        DB::table('business_user')->orderBy('id')->chunkById(100, function ($memberships): void {
            foreach ($memberships as $membership) {
                $role = (string) $membership->role;
                $rawPermissions = $membership->permissions ?? null;

                $flatPermissions = null;

                if (is_string($rawPermissions) && $rawPermissions !== '') {
                    $decoded = json_decode($rawPermissions, true);
                    $flatPermissions = is_array($decoded) ? $decoded : null;
                } elseif (is_array($rawPermissions)) {
                    $flatPermissions = $rawPermissions;
                }

                $rolePermissions = is_array($flatPermissions) && array_is_list($flatPermissions)
                    ? $flatPermissions
                    : (is_array($flatPermissions) && isset($flatPermissions[$role])
                        ? $flatPermissions[$role]
                        : null);

                if (! is_array($rolePermissions)) {
                    $rolePermissions = [];
                }

                DB::table('business_user')
                    ->where('id', $membership->id)
                    ->update([
                        'roles' => json_encode([$role]),
                        'permissions' => json_encode([$role => array_values(array_map('strval', $rolePermissions))]),
                    ]);
            }
        });

        Schema::table('business_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->string('role')->nullable()->after('user_id');
        });

        DB::table('business_user')->orderBy('id')->chunkById(100, function ($memberships): void {
            foreach ($memberships as $membership) {
                $roles = json_decode((string) $membership->roles, true);
                $permissions = json_decode((string) $membership->permissions, true);

                $role = is_array($roles) && $roles !== []
                    ? (string) $roles[0]
                    : 'viewer';

                $flat = [];

                if (is_array($permissions)) {
                    if (array_is_list($permissions)) {
                        $flat = $permissions;
                    } elseif (isset($permissions[$role]) && is_array($permissions[$role])) {
                        $flat = $permissions[$role];
                    } else {
                        foreach ($permissions as $rolePermissions) {
                            if (is_array($rolePermissions)) {
                                $flat = array_merge($flat, $rolePermissions);
                            }
                        }
                        $flat = array_values(array_unique($flat));
                    }
                }

                DB::table('business_user')
                    ->where('id', $membership->id)
                    ->update([
                        'role' => $role,
                        'permissions' => json_encode(array_values(array_map('strval', $flat))),
                    ]);
            }
        });

        Schema::table('business_user', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
