<?php

namespace App\Support\Accounting;

use App\Support\RichText;

final class PreviewDocumentItem
{
    public function __construct(
        public string $title,
        public string $quantity,
        public string $unit_price,
        public string $total,
        public ?string $sku = null,
        public ?string $unit = null,
        public string $discount_amount = '0',
        public string $tax_amount = '0',
    ) {}

    public function titleHtml(): string
    {
        return RichText::sanitize(RichText::fromPlain($this->title));
    }
}
