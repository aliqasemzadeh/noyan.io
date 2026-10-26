<?php

namespace App\Support\Navigation;

use App\Enums\Business\BusinessPermission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SidebarMenu
{
    /**
     * Leaf navigation items the current user may open, for the active panel.
     *
     * @return list<array{label: string, icon: string, href: string}>
     */
    public static function searchItems(?User $user = null): array
    {
        $user ??= Auth::user();

        if ($user === null) {
            return [];
        }

        return match (true) {
            request()->routeIs('system.*') => self::systemItems($user),
            request()->routeIs('user.*') => self::userItems(),
            request()->routeIs('warehouse.*') => self::warehouseItems($user),
            request()->routeIs('sale.*') => self::saleItems($user),
            request()->routeIs('accounting.*') => self::accountingItems(),
            default => [],
        };
    }

    /**
     * @return list<array{label: string, icon: string, href: string}>
     */
    private static function systemItems(User $user): array
    {
        $items = [];

        if ($user->can('dashboard_view')) {
            $items[] = self::item(__('general.dashboard'), 'home', route('system.dashboard'));
        }

        if ($user->can('user_view')) {
            $items[] = self::item(__('general.users'), 'users', route('system.users.index'));
        }

        if ($user->can('role_view')) {
            $items[] = self::item(__('general.roles'), 'shield', route('system.roles.index'));
        }

        if ($user->can('permission_view')) {
            $items[] = self::item(__('general.permissions'), 'key-round', route('system.permissions.index'));
        }

        if ($user->can('function_view')) {
            $items[] = self::item(__('general.system_functions'), 'terminal', route('system.functions.index'));
        }

        if ($user->can('backup_view')) {
            $items[] = self::item(__('general.system_backups'), 'database', route('system.backups.index'));
        }

        if ($user->can('setting_view')) {
            $items[] = self::item(__('general.system_settings'), 'settings', route('system.settings.index'));
        }

        if ($user->can('ticket_view')) {
            $items[] = self::item(__('general.tickets'), 'tickets', route('system.support.tickets.index'));
        }

        if ($user->can('ticket_category_view')) {
            $items[] = self::item(__('general.ticket_categories'), 'folder-kanban', route('system.support.ticket-categories.index'));
        }

        if ($user->can('business_view')) {
            $items[] = self::item(__('general.businesses'), 'building', route('system.businesses.index'));
        }

        if ($user->can('currency_view')) {
            $items[] = self::item(__('general.currencies'), 'coins', route('system.currencies.index'));
        }

        if ($user->can('category_view')) {
            $items[] = self::item(__('general.system_categories'), 'folder-tree', route('system.categories.index'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, icon: string, href: string}>
     */
    private static function userItems(): array
    {
        return [
            self::item(__('general.dashboard'), 'home', route('user.dashboard')),
            self::item(__('general.my_businesses'), 'building', route('user.businesses.index')),
            self::item(__('general.support'), 'life-buoy', route('user.support.tickets.index')),
            self::item(__('general.user_settings'), 'circle-user', route('user.profile')),
        ];
    }

    /**
     * @return list<array{label: string, icon: string, href: string}>
     */
    private static function warehouseItems(User $user): array
    {
        $items = [
            self::item(__('general.dashboard'), 'home', route('warehouse.dashboard')),
        ];

        if ($user->canInBusiness(BusinessPermission::StockMovementView)) {
            $items[] = self::item(__('general.inventory_stocks'), 'boxes', route('warehouse.stocks.index'));
            $items[] = self::item(__('general.stock_movements'), 'arrow-left-right', route('warehouse.movements.index'));
            $items[] = self::item(__('general.stock_conversions'), 'refresh-cw', route('warehouse.conversions.index'));
        }

        if ($user->canInBusiness(BusinessPermission::WarehouseView)) {
            $items[] = self::item(__('general.warehouses'), 'warehouse', route('warehouse.warehouses.index'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, icon: string, href: string}>
     */
    private static function saleItems(User $user): array
    {
        $items = [
            self::item(__('general.dashboard'), 'home', route('sale.dashboard')),
        ];

        if ($user->canInBusiness(BusinessPermission::InvoiceView)) {
            $items[] = self::item(__('general.invoices'), 'file-text', route('sale.invoices.index'));
        }

        if ($user->canInBusiness(BusinessPermission::QuoteView)) {
            $items[] = self::item(__('general.quotes'), 'file-pen-line', route('sale.quotes.index'));
        }

        if ($user->canInBusiness(BusinessPermission::PartyView)) {
            $items[] = self::item(__('general.parties'), 'users', route('sale.parties.index'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, icon: string, href: string}>
     */
    private static function accountingItems(): array
    {
        return [
            self::item(__('general.dashboard'), 'home', route('accounting.dashboard')),
            self::item(__('general.journal_entries'), 'book-open', route('accounting.journal-entries.index')),
            self::item(__('general.fiscal_years'), 'calendar', route('accounting.fiscal-years.index')),
            self::item(__('general.cost_centers'), 'landmark', route('accounting.cost-centers.index')),
            self::item(__('general.projects'), 'folder-kanban', route('accounting.projects.index')),
            self::item(__('general.cash_and_bank_accounts'), 'wallet', route('accounting.accounts.index')),
            self::item(__('general.categories'), 'folder', route('accounting.categories.index')),
            self::item(__('general.business_currencies'), 'coins', route('accounting.currencies.index')),
            self::item(__('general.sale_kinds'), 'tags', route('accounting.sale-kinds.index')),
            self::item(__('general.settlement_types'), 'handshake', route('accounting.settlement-types.index')),
            self::item(__('general.products'), 'package', route('accounting.catalog.products.index')),
            self::item(__('general.categories'), 'folder-tree', route('accounting.catalog.categories.index')),
            self::item(__('general.brands'), 'award', route('accounting.catalog.brands.index')),
            self::item(__('general.invoices'), 'file-text', route('accounting.invoices.index')),
            self::item(__('general.quotes'), 'file-pen-line', route('accounting.quotes.index')),
            self::item(__('general.transactions'), 'arrow-left-right', route('accounting.transactions.index')),
            self::item(__('general.debts_and_loans'), 'hand-coins', route('accounting.loans.index')),
            self::item(__('general.cheques'), 'banknote', route('accounting.cheques.index')),
            self::item(__('general.parties'), 'users', route('accounting.parties.index')),
        ];
    }

    /**
     * @return array{label: string, icon: string, href: string}
     */
    private static function item(string $label, string $icon, string $href): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'href' => $href,
        ];
    }
}
