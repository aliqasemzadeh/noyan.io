<?php

namespace App\Enums\Business;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Seller = 'seller';
    case Viewer = 'viewer';

    public function label(): string
    {
        $key = 'general.business_role_'.$this->value;
        $translated = __($key);

        return $translated === $key ? $this->name : $translated;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner, self::Admin => BusinessPermission::values(),
            self::Seller => [
                BusinessPermission::QuoteView->value,
                BusinessPermission::QuoteCreate->value,
                BusinessPermission::QuoteEdit->value,
                BusinessPermission::QuoteDelete->value,
                BusinessPermission::InvoiceView->value,
                BusinessPermission::InvoiceCreate->value,
                BusinessPermission::InvoiceEdit->value,
                BusinessPermission::InvoiceDelete->value,
                BusinessPermission::PartyView->value,
                BusinessPermission::PartyCreate->value,
            ],
            self::Accountant, self::Viewer => [],
        };
    }

    public function canAccessPanel(BusinessPanel $panel): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Seller => $panel === BusinessPanel::Sale,
            self::Accountant, self::Viewer => $panel === BusinessPanel::Accounting,
        };
    }

    public function hasFullBusinessAccess(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
