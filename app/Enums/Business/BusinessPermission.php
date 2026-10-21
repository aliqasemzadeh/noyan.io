<?php

namespace App\Enums\Business;

enum BusinessPermission: string
{
    case QuoteView = 'quote_view';
    case QuoteCreate = 'quote_create';
    case QuoteEdit = 'quote_edit';
    case QuoteDelete = 'quote_delete';

    case InvoiceView = 'invoice_view';
    case InvoiceCreate = 'invoice_create';
    case InvoiceEdit = 'invoice_edit';
    case InvoiceDelete = 'invoice_delete';

    case PartyView = 'party_view';
    case PartyCreate = 'party_create';
    case PartyEdit = 'party_edit';
    case PartyDelete = 'party_delete';

    public function label(): string
    {
        return __('general.business_permission_'.$this->value);
    }

    public function group(): string
    {
        return match ($this) {
            self::QuoteView, self::QuoteCreate, self::QuoteEdit, self::QuoteDelete => 'quote',
            self::InvoiceView, self::InvoiceCreate, self::InvoiceEdit, self::InvoiceDelete => 'invoice',
            self::PartyView, self::PartyCreate, self::PartyEdit, self::PartyDelete => 'party',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }
}
