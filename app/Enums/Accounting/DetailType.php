<?php

namespace App\Enums\Accounting;

enum DetailType: string
{
    case Party = 'party';
    case Account = 'account';
    case CostCenter = 'cost_center';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Party => __('general.detail_type_party'),
            self::Account => __('general.detail_type_account'),
            self::CostCenter => __('general.detail_type_cost_center'),
            self::Project => __('general.detail_type_project'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
