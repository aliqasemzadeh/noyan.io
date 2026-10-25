<?php

namespace App\Enums\Catalog;

enum WarehouseType: string
{
    case Main = 'main';
    case Consumable = 'consumable';
    case Project = 'project';
    case Production = 'production';
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Main => __('general.warehouse_type_main'),
            self::Consumable => __('general.warehouse_type_consumable'),
            self::Project => __('general.warehouse_type_project'),
            self::Production => __('general.warehouse_type_production'),
            self::Waste => __('general.warehouse_type_waste'),
        };
    }

    public function isSellableByDefault(): bool
    {
        return $this === self::Main;
    }

    public function defaultCode(): string
    {
        return strtoupper($this->value);
    }

    /**
     * @return list<self>
     */
    public static function businessPresets(): array
    {
        return [
            self::Main,
            self::Consumable,
            self::Project,
            self::Production,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
