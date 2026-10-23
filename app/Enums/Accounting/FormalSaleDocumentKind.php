<?php

namespace App\Enums\Accounting;

enum FormalSaleDocumentKind: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Quote => __('general.quote'),
            self::Invoice => __('general.invoice'),
        };
    }
}
