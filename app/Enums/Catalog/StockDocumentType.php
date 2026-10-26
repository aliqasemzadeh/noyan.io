<?php

namespace App\Enums\Catalog;

enum StockDocumentType: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => __('general.dispatches'),
            self::Inbound => __('general.receipts'),
            self::Transfer => __('general.stock_transfers'),
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Outbound => 'OUT',
            self::Inbound => 'IN',
            self::Transfer => 'TR',
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
