<?php

namespace App\Enums\Catalog;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Reserve = 'reserve';
    case ReleaseReserve = 'release_reserve';
    case Consume = 'consume';
    case Produce = 'produce';

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('general.stock_movement_opening'),
            self::Purchase => __('general.stock_movement_purchase'),
            self::Sale => __('general.stock_movement_sale'),
            self::ReturnIn => __('general.stock_movement_return_in'),
            self::ReturnOut => __('general.stock_movement_return_out'),
            self::Adjustment => __('general.stock_movement_adjustment'),
            self::TransferIn => __('general.stock_movement_transfer_in'),
            self::TransferOut => __('general.stock_movement_transfer_out'),
            self::Reserve => __('general.stock_movement_reserve'),
            self::ReleaseReserve => __('general.stock_movement_release_reserve'),
            self::Consume => __('general.stock_movement_consume'),
            self::Produce => __('general.stock_movement_produce'),
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
