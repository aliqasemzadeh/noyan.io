<?php

namespace App\Support;

class AdministratorPermissions
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        /** @var array<string, string> $permissions */
        $permissions = trans('permissions.administrator');

        return array_keys($permissions);
    }

    public static function label(string $name): string
    {
        return __('permissions.administrator.'.$name);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::names() as $name) {
            $prefix = strstr($name, '_', true) ?: $name;
            $groups[$prefix][] = $name;
        }

        return $groups;
    }

    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'dashboard' => __('general.dashboard'),
            'user' => __('general.users'),
            'role' => __('general.roles'),
            'permission' => __('general.permissions'),
            'business' => __('general.businesses'),
            'currency' => __('general.currencies'),
            'category' => __('general.system_categories'),
            default => ucfirst($group),
        };
    }
}
