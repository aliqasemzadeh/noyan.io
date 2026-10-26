<?php

namespace App\Support\Warehouse;

use Illuminate\Support\Facades\Cache;

class WarehouseDashboardCache
{
    public static function version(int $businessId): int
    {
        return (int) Cache::get(self::key($businessId), 1);
    }

    public static function bump(int $businessId): void
    {
        $key = self::key($businessId);
        $current = (int) Cache::get($key, 1);

        Cache::forever($key, $current + 1);
    }

    protected static function key(int $businessId): string
    {
        return 'warehouse-dashboard-version.'.$businessId;
    }
}
