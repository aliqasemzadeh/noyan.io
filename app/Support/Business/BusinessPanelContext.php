<?php

namespace App\Support\Business;

use App\Enums\Business\BusinessPanel;

class BusinessPanelContext
{
    public static function current(): BusinessPanel
    {
        if (request()->routeIs('sale.*')) {
            return BusinessPanel::Sale;
        }

        if (request()->routeIs('warehouse.*')) {
            return BusinessPanel::Warehouse;
        }

        return BusinessPanel::Accounting;
    }

    public static function isSale(): bool
    {
        return self::current() === BusinessPanel::Sale;
    }

    public static function isAccounting(): bool
    {
        return self::current() === BusinessPanel::Accounting;
    }

    public static function isWarehouse(): bool
    {
        return self::current() === BusinessPanel::Warehouse;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $parameters
     */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return route(self::current()->value.'.'.$name, $parameters, $absolute);
    }

    public static function routeName(string $name): string
    {
        return self::current()->value.'.'.$name;
    }

    public static function event(string $suffix): string
    {
        return 'panels.'.self::current()->value.'.'.$suffix;
    }
}
