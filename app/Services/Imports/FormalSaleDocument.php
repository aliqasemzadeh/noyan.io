<?php

namespace App\Services\Imports;

use App\Enums\Accounting\FormalSaleDocumentKind;

readonly class FormalSaleDocument
{
    /**
     * @param  list<array{
     *     title: string,
     *     sku: string|null,
     *     unit: string|null,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount: string,
     *     tax_amount: string
     * }>  $items
     */
    public function __construct(
        public FormalSaleDocumentKind $kind,
        public string $title,
        public ?string $serial,
        public ?string $issueDate,
        public ?string $partyName,
        public ?string $partyEconomicCode,
        public ?string $partyNationalId,
        public ?string $partyPostalCode,
        public ?string $partyPhone,
        public ?string $partyAddress,
        public array $items,
    ) {}
}
