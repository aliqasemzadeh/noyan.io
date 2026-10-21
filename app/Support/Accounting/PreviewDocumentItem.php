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
    ) {}

    public function titleHtml(): string
    {
        return RichText::fromPlain($this->title);
    }
}
