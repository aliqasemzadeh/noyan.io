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

    public function description(): string
    {
        return __('general.business_role_'.$this->value.'_description');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::Owner,
        ));
    }

    /**
     * @return list<BusinessPermission>
     */
    public function applicablePermissions(): array
    {
        return match ($this) {
            self::Owner, self::Admin => BusinessPermission::all(),
            self::Accountant => array_values(array_filter(
                BusinessPermission::all(),
                fn (BusinessPermission $permission): bool => in_array($permission->group(), [
                    'account',
                    'transaction',
                    'journal_entry',
                    'loan',
                    'cheque',
                    'fiscal_year',
                    'cost_center',
                    'project',
                    'currency',
                    'category',
                    'product',
                    'brand',
                    'party',
                    'invoice',
                    'quote',
                ], true),
            )),
            self::Seller => array_values(array_filter(
                BusinessPermission::all(),
                fn (BusinessPermission $permission): bool => in_array($permission->group(), [
                    'quote',
                    'invoice',
                    'party',
                    'product',
                ], true),
            )),
            self::Viewer => array_values(array_filter(
                BusinessPermission::all(),
                fn (BusinessPermission $permission): bool => str_ends_with($permission->value, '_view'),
            )),
        };
    }

    /**
     * @return list<string>
     */
    public function applicablePermissionValues(): array
    {
        return BusinessPermission::valuesOf($this->applicablePermissions());
    }

    /**
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        if ($this === self::Owner) {
            return BusinessPermission::values();
        }

        return array_values(array_filter(
            $this->applicablePermissionValues(),
            fn (string $permission): bool => ! str_ends_with($permission, '_delete'),
        ));
    }

    /**
     * @return array<string, list<BusinessPermission>>
     */
    public function applicablePermissionGroups(): array
    {
        return BusinessPermission::groupedFrom($this->applicablePermissions());
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
        return $this === self::Owner;
    }
}
