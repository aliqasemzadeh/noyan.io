<?php

use App\Enums\Business\BusinessPermission;
use App\Support\Navigation\SidebarMenu;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @return list<array{label: string, icon: string, href: string}>
     */
    #[Computed]
    public function searchItems(): array
    {
        return SidebarMenu::searchItems(auth()->user());
    }

    public function searchShortcutLabel(): string
    {
        $ua = (string) request()->userAgent();

        if (str_contains($ua, 'Mac OS') || str_contains($ua, 'Macintosh')) {
            return '⌘K';
        }

        return 'Ctrl+K';
    }
};
?>

<div class="flex flex-col gap-2">
    <div class="px-2">
        <flux:modal.trigger name="sidebar.menu-search" shortcut="cmd.k">
            <flux:sidebar.search
                :placeholder="__('general.search_menu')"
                :kbd="$this->searchShortcutLabel()"
            />
        </flux:modal.trigger>
    </div>

    <flux:modal
        name="sidebar.menu-search"
        variant="bare"
        class="w-full max-w-[30rem] my-[12vh] max-h-screen overflow-y-hidden"
    >
        <flux:command class="border-none shadow-lg inline-flex flex-col max-h-[76vh]">
            <flux:command.input :placeholder="__('general.search_menu')" closable />

            <flux:command.items>
                @foreach ($this->searchItems as $item)
                    <flux:command.item
                        :icon="$item['icon']"
                        :href="$item['href']"
                        wire:navigate
                    >
                        {{ $item['label'] }}
                    </flux:command.item>
                @endforeach

                <flux:command.empty>{{ __('general.search_menu_empty') }}</flux:command.empty>
            </flux:command.items>
        </flux:command>
    </flux:modal>

    <flux:sidebar.nav>
        @if (request()->routeIs('system.*'))
            @can('dashboard_view')
                <flux:sidebar.item
                    icon="home"
                    :href="route('system.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('system.dashboard')"
                    data-nav-label="{{ __('general.dashboard') }}"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            @endcan

            @if (auth()->user()?->can('user_view') || auth()->user()?->can('role_view') || auth()->user()?->can('permission_view'))
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('system.users.*') || request()->routeIs('system.roles.*') || request()->routeIs('system.permissions.*')"
                    icon="users"
                    heading="{{ __('general.user_management') }}"
                    class="grid"
                >
                    @can('user_view')
                        <flux:sidebar.item
                            :href="route('system.users.index')"
                            wire:navigate
                            :current="request()->routeIs('system.users.*')"
                            data-nav-label="{{ __('general.users') }}"
                        >
                            {{ __('general.users') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('role_view')
                        <flux:sidebar.item
                            :href="route('system.roles.index')"
                            wire:navigate
                            :current="request()->routeIs('system.roles.*')"
                            data-nav-label="{{ __('general.roles') }}"
                        >
                            {{ __('general.roles') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('permission_view')
                        <flux:sidebar.item
                            :href="route('system.permissions.index')"
                            wire:navigate
                            :current="request()->routeIs('system.permissions.*')"
                            data-nav-label="{{ __('general.permissions') }}"
                        >
                            {{ __('general.permissions') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if (auth()->user()?->can('function_view') || auth()->user()?->can('backup_view') || auth()->user()?->can('setting_view'))
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('system.functions.*') || request()->routeIs('system.backups.*') || request()->routeIs('system.settings.*')"
                    icon="wrench"
                    heading="{{ __('general.system_tools') }}"
                    class="grid"
                >
                    @can('function_view')
                        <flux:sidebar.item
                            icon="terminal"
                            :href="route('system.functions.index')"
                            wire:navigate
                            :current="request()->routeIs('system.functions.*')"
                            data-nav-label="{{ __('general.system_functions') }}"
                        >
                            {{ __('general.system_functions') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('backup_view')
                        <flux:sidebar.item
                            icon="database"
                            :href="route('system.backups.index')"
                            wire:navigate
                            :current="request()->routeIs('system.backups.*')"
                            data-nav-label="{{ __('general.system_backups') }}"
                        >
                            {{ __('general.system_backups') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('setting_view')
                        <flux:sidebar.item
                            icon="settings"
                            :href="route('system.settings.index')"
                            wire:navigate
                            :current="request()->routeIs('system.settings.*')"
                            data-nav-label="{{ __('general.system_settings') }}"
                        >
                            {{ __('general.system_settings') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @if (auth()->user()?->can('ticket_view') || auth()->user()?->can('ticket_category_view'))
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('system.support.*')"
                    icon="life-buoy"
                    heading="{{ __('general.support') }}"
                    class="grid"
                >
                    @can('ticket_view')
                        <flux:sidebar.item
                            icon="tickets"
                            :href="route('system.support.tickets.index')"
                            wire:navigate
                            :current="request()->routeIs('system.support.tickets.*')"
                            data-nav-label="{{ __('general.tickets') }}"
                        >
                            {{ __('general.tickets') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('ticket_category_view')
                        <flux:sidebar.item
                            icon="folder-kanban"
                            :href="route('system.support.ticket-categories.index')"
                            wire:navigate
                            :current="request()->routeIs('system.support.ticket-categories.*')"
                            data-nav-label="{{ __('general.ticket_categories') }}"
                        >
                            {{ __('general.ticket_categories') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

            @can('business_view')
                <flux:sidebar.item
                    icon="building"
                    :href="route('system.businesses.index')"
                    wire:navigate
                    :current="request()->routeIs('system.businesses.*')"
                    data-nav-label="{{ __('general.businesses') }}"
                >
                    {{ __('general.businesses') }}
                </flux:sidebar.item>
            @endcan

            @can('currency_view')
                <flux:sidebar.item
                    icon="coins"
                    :href="route('system.currencies.index')"
                    wire:navigate
                    :current="request()->routeIs('system.currencies.*')"
                    data-nav-label="{{ __('general.currencies') }}"
                >
                    {{ __('general.currencies') }}
                </flux:sidebar.item>
            @endcan

            @can('category_view')
                <flux:sidebar.item
                    icon="folder-tree"
                    :href="route('system.categories.index')"
                    wire:navigate
                    :current="request()->routeIs('system.categories.*')"
                    data-nav-label="{{ __('general.system_categories') }}"
                >
                    {{ __('general.system_categories') }}
                </flux:sidebar.item>
            @endcan
        @elseif (request()->routeIs('user.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('user.dashboard')"
                wire:navigate
                :current="request()->routeIs('user.dashboard')"
                data-nav-label="{{ __('general.dashboard') }}"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="building"
                :href="route('user.businesses.index')"
                wire:navigate
                :current="request()->routeIs('user.businesses.*')"
                data-nav-label="{{ __('general.my_businesses') }}"
            >
                {{ __('general.my_businesses') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="life-buoy"
                :href="route('user.support.tickets.index')"
                wire:navigate
                :current="request()->routeIs('user.support.*')"
                data-nav-label="{{ __('general.support') }}"
            >
                {{ __('general.support') }}
            </flux:sidebar.item>
        @elseif (request()->routeIs('warehouse.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('warehouse.dashboard')"
                wire:navigate
                :current="request()->routeIs('warehouse.dashboard')"
                data-nav-label="{{ __('general.dashboard') }}"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive) || auth()->user()?->canInBusiness(BusinessPermission::StockIssue) || auth()->user()?->canInBusiness(BusinessPermission::StockTransfer) || auth()->user()?->canInBusiness(BusinessPermission::StockAdjust) || auth()->user()?->canInBusiness(BusinessPermission::StockAllocate))
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('warehouse.stocks.*')"
                    icon="package-open"
                    heading="{{ __('general.stock_operations') }}"
                    class="grid"
                >
                    @if (auth()->user()?->canInBusiness(BusinessPermission::StockMovementView))
                        <flux:sidebar.item
                            :href="route('warehouse.stocks.index')"
                            wire:navigate
                            :current="request()->routeIs('warehouse.stocks.*')"
                            data-nav-label="{{ __('general.inventory_stocks') }}"
                        >
                            {{ __('general.inventory_stocks') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::StockMovementView))
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('warehouse.stocks.*') || request()->routeIs('warehouse.movements.*') || request()->routeIs('warehouse.conversions.*')"
                    icon="boxes"
                    heading="{{ __('general.inventory') }}"
                    class="grid"
                >
                    <flux:sidebar.item
                        :href="route('warehouse.stocks.index')"
                        wire:navigate
                        :current="request()->routeIs('warehouse.stocks.*')"
                        data-nav-label="{{ __('general.inventory_stocks') }}"
                    >
                        {{ __('general.inventory_stocks') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        :href="route('warehouse.movements.index')"
                        wire:navigate
                        :current="request()->routeIs('warehouse.movements.*')"
                        data-nav-label="{{ __('general.stock_movements') }}"
                    >
                        {{ __('general.stock_movements') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        :href="route('warehouse.conversions.index')"
                        wire:navigate
                        :current="request()->routeIs('warehouse.conversions.*')"
                        data-nav-label="{{ __('general.stock_conversions') }}"
                    >
                        {{ __('general.stock_conversions') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseView))
                <flux:sidebar.item
                    icon="warehouse"
                    :href="route('warehouse.warehouses.index')"
                    wire:navigate
                    :current="request()->routeIs('warehouse.warehouses.*')"
                    data-nav-label="{{ __('general.warehouses') }}"
                >
                    {{ __('general.warehouses') }}
                </flux:sidebar.item>
            @endif
        @elseif (request()->routeIs('sale.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('sale.dashboard')"
                wire:navigate
                :current="request()->routeIs('sale.dashboard')"
                data-nav-label="{{ __('general.dashboard') }}"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceView))
                <flux:sidebar.item
                    icon="file-text"
                    :href="route('sale.invoices.index')"
                    wire:navigate
                    :current="request()->routeIs('sale.invoices.*')"
                    data-nav-label="{{ __('general.invoices') }}"
                >
                    {{ __('general.invoices') }}
                </flux:sidebar.item>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::QuoteView))
                <flux:sidebar.item
                    icon="file-pen-line"
                    :href="route('sale.quotes.index')"
                    wire:navigate
                    :current="request()->routeIs('sale.quotes.*')"
                    data-nav-label="{{ __('general.quotes') }}"
                >
                    {{ __('general.quotes') }}
                </flux:sidebar.item>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::PartyView))
                <flux:sidebar.item
                    icon="users"
                    :href="route('sale.parties.index')"
                    wire:navigate
                    :current="request()->routeIs('sale.parties.*')"
                    data-nav-label="{{ __('general.parties') }}"
                >
                    {{ __('general.parties') }}
                </flux:sidebar.item>
            @endif
        @elseif (request()->routeIs('accounting.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('accounting.dashboard')"
                wire:navigate
                :current="request()->routeIs('accounting.dashboard')"
                data-nav-label="{{ __('general.dashboard') }}"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            <flux:sidebar.group
                expandable
                :expanded="request()->routeIs('accounting.journal-entries.*') || request()->routeIs('accounting.fiscal-years.*') || request()->routeIs('accounting.cost-centers.*') || request()->routeIs('accounting.projects.*')"
                icon="book-open"
                heading="{{ __('general.general_ledger') }}"
                class="grid"
            >
                <flux:sidebar.item
                    :href="route('accounting.journal-entries.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.journal-entries.*')"
                    data-nav-label="{{ __('general.journal_entries') }}"
                >
                    {{ __('general.journal_entries') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.fiscal-years.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.fiscal-years.*')"
                    data-nav-label="{{ __('general.fiscal_years') }}"
                >
                    {{ __('general.fiscal_years') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.cost-centers.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.cost-centers.*')"
                    data-nav-label="{{ __('general.cost_centers') }}"
                >
                    {{ __('general.cost_centers') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.projects.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.projects.*')"
                    data-nav-label="{{ __('general.projects') }}"
                >
                    {{ __('general.projects') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group
                expandable
                :expanded="request()->routeIs('accounting.accounts.*') || request()->routeIs('accounting.currencies.*') || request()->routeIs('accounting.categories.*') || request()->routeIs('accounting.sale-kinds.*') || request()->routeIs('accounting.settlement-types.*')"
                icon="wallet"
                heading="{{ __('general.treasury') }}"
                class="grid"
            >
                <flux:sidebar.item
                    :href="route('accounting.accounts.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.accounts.*')"
                    data-nav-label="{{ __('general.cash_and_bank_accounts') }}"
                >
                    {{ __('general.cash_and_bank_accounts') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.categories.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.categories.*')"
                    data-nav-label="{{ __('general.categories') }}"
                >
                    {{ __('general.categories') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.currencies.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.currencies.*')"
                    data-nav-label="{{ __('general.business_currencies') }}"
                >
                    {{ __('general.business_currencies') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.sale-kinds.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.sale-kinds.*')"
                    data-nav-label="{{ __('general.sale_kinds') }}"
                >
                    {{ __('general.sale_kinds') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.settlement-types.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.settlement-types.*')"
                    data-nav-label="{{ __('general.settlement_types') }}"
                >
                    {{ __('general.settlement_types') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group
                expandable
                :expanded="request()->routeIs('accounting.catalog.*')"
                icon="package"
                heading="{{ __('general.catalog') }}"
                class="grid"
            >
                <flux:sidebar.item
                    :href="route('accounting.catalog.products.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.products.*')"
                    data-nav-label="{{ __('general.products') }}"
                >
                    {{ __('general.products') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.catalog.categories.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.categories.*')"
                    data-nav-label="{{ __('general.categories') }}"
                >
                    {{ __('general.categories') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.catalog.brands.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.brands.*')"
                    data-nav-label="{{ __('general.brands') }}"
                >
                    {{ __('general.brands') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.item
                icon="file-text"
                :href="route('accounting.invoices.index')"
                wire:navigate
                :current="request()->routeIs('accounting.invoices.*')"
                data-nav-label="{{ __('general.invoices') }}"
            >
                {{ __('general.invoices') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="file-pen-line"
                :href="route('accounting.quotes.index')"
                wire:navigate
                :current="request()->routeIs('accounting.quotes.*')"
                data-nav-label="{{ __('general.quotes') }}"
            >
                {{ __('general.quotes') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="arrow-left-right"
                :href="route('accounting.transactions.index')"
                wire:navigate
                :current="request()->routeIs('accounting.transactions.*')"
                data-nav-label="{{ __('general.transactions') }}"
            >
                {{ __('general.transactions') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="hand-coins"
                :href="route('accounting.loans.index')"
                wire:navigate
                :current="request()->routeIs('accounting.loans.*')"
                data-nav-label="{{ __('general.debts_and_loans') }}"
            >
                {{ __('general.debts_and_loans') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="banknote"
                :href="route('accounting.cheques.index')"
                wire:navigate
                :current="request()->routeIs('accounting.cheques.*')"
                data-nav-label="{{ __('general.cheques') }}"
            >
                {{ __('general.cheques') }}
            </flux:sidebar.item>

            <flux:sidebar.group
                expandable
                :expanded="request()->routeIs('accounting.parties.*')"
                icon="users"
                heading="{{ __('general.parties') }}"
                class="grid"
            >
                <flux:sidebar.item
                    :href="route('accounting.parties.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.parties.*')"
                    data-nav-label="{{ __('general.parties') }}"
                >
                    {{ __('general.parties') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        @endif
    </flux:sidebar.nav>
</div>
