<?php

namespace App\Enums\Catalog;

enum StockDocumentStatus: string
{
    case PendingPick = 'pending_pick';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
    case Expected = 'expected';
    case Received = 'received';
    case Discrepancy = 'discrepancy';
    case InTransit = 'in_transit';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('general.stock_document_status_'.$this->value);
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::PendingPick, self::Expected => 'amber',
            self::Packed, self::InTransit => 'sky',
            self::Dispatched, self::Received => 'teal',
            self::Discrepancy => 'rose',
            self::Cancelled => 'zinc',
        };
    }

    public function allowsInvoiceQuantityEdit(): bool
    {
        return match ($this) {
            self::PendingPick, self::Packed, self::Expected => true,
            default => false,
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
