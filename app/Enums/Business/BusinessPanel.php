<?php

namespace App\Enums\Business;

enum BusinessPanel: string
{
    case Accounting = 'accounting';
    case Warehouse = 'warehouse';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Accounting => __('general.accounting'),
            self::Warehouse => __('general.warehouse'),
            self::Sale => __('general.sale'),
        };
    }

    public function dashboardRoute(): string
    {
        return $this->value.'.dashboard';
    }
}
