<?php

use App\Enums\Business\BusinessPermission;
use Livewire\Component;

new class extends Component
{
    //
};
?>

<div
    class="flex flex-col gap-2"
    x-data="{
        search: '',
        match(label) {
            const q = this.search.trim().toLowerCase();
            if (q === '') return true;
            return String(label).toLowerCase().includes(q);
        },
        groupVisible(...labels) {
            const q = this.search.trim().toLowerCase();
            if (q === '') return true;
            return labels.some((label) => String(label).toLowerCase().includes(q));
        },
    }"
>
    <div class="px-2">
        <flux:input
            x-model.debounce.200ms="search"
            icon="magnifying-glass"
            size="sm"
            clearable
            :placeholder="__('general.search_menu')"
            autocomplete="off"
        />
    </div>

    <flux:sidebar.nav>
        @if (request()->routeIs('system.*'))
            @can('dashboard_view')
                <div x-show="match(@js(__('general.dashboard')))" x-cloak>
                    <flux:sidebar.item
                        icon="home"
                        :href="route('system.dashboard')"
                        wire:navigate
                        :current="request()->routeIs('system.dashboard')"
                        data-nav-label="{{ __('general.dashboard') }}"
                    >
                        {{ __('general.dashboard') }}
                    </flux:sidebar.item>
                </div>
            @endcan

            @if (auth()->user()?->can('user_view') || auth()->user()?->can('role_view') || auth()->user()?->can('permission_view'))
                <div
                    x-show="groupVisible(
                        @js(__('general.user_management')),
                        @js(__('general.users')),
                        @js(__('general.roles')),
                        @js(__('general.permissions'))
                    )"
                    x-cloak
                >
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('system.users.*') || request()->routeIs('system.roles.*') || request()->routeIs('system.permissions.*')"
                        icon="users"
                        heading="{{ __('general.user_management') }}"
                        class="grid"
                    >
                        @can('user_view')
                            <div x-show="match(@js(__('general.users')))" x-cloak>
                                <flux:sidebar.item
                                    :href="route('system.users.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.users.*')"
                                    data-nav-label="{{ __('general.users') }}"
                                >
                                    {{ __('general.users') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan

                        @can('role_view')
                            <div x-show="match(@js(__('general.roles')))" x-cloak>
                                <flux:sidebar.item
                                    :href="route('system.roles.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.roles.*')"
                                    data-nav-label="{{ __('general.roles') }}"
                                >
                                    {{ __('general.roles') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan

                        @can('permission_view')
                            <div x-show="match(@js(__('general.permissions')))" x-cloak>
                                <flux:sidebar.item
                                    :href="route('system.permissions.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.permissions.*')"
                                    data-nav-label="{{ __('general.permissions') }}"
                                >
                                    {{ __('general.permissions') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan
                    </flux:sidebar.group>
                </div>
            @endif

            @if (auth()->user()?->can('function_view') || auth()->user()?->can('backup_view') || auth()->user()?->can('setting_view'))
                <div
                    x-show="groupVisible(
                        @js(__('general.system_tools')),
                        @js(__('general.system_functions')),
                        @js(__('general.system_backups')),
                        @js(__('general.system_settings'))
                    )"
                    x-cloak
                >
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('system.functions.*') || request()->routeIs('system.backups.*') || request()->routeIs('system.settings.*')"
                        icon="wrench"
                        heading="{{ __('general.system_tools') }}"
                        class="grid"
                    >
                        @can('function_view')
                            <div x-show="match(@js(__('general.system_functions')))" x-cloak>
                                <flux:sidebar.item
                                    icon="terminal"
                                    :href="route('system.functions.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.functions.*')"
                                    data-nav-label="{{ __('general.system_functions') }}"
                                >
                                    {{ __('general.system_functions') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan

                        @can('backup_view')
                            <div x-show="match(@js(__('general.system_backups')))" x-cloak>
                                <flux:sidebar.item
                                    icon="database"
                                    :href="route('system.backups.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.backups.*')"
                                    data-nav-label="{{ __('general.system_backups') }}"
                                >
                                    {{ __('general.system_backups') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan

                        @can('setting_view')
                            <div x-show="match(@js(__('general.system_settings')))" x-cloak>
                                <flux:sidebar.item
                                    icon="settings"
                                    :href="route('system.settings.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.settings.*')"
                                    data-nav-label="{{ __('general.system_settings') }}"
                                >
                                    {{ __('general.system_settings') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan
                    </flux:sidebar.group>
                </div>
            @endif

            @if (auth()->user()?->can('ticket_view') || auth()->user()?->can('ticket_category_view'))
                <div
                    x-show="groupVisible(
                        @js(__('general.support')),
                        @js(__('general.tickets')),
                        @js(__('general.ticket_categories'))
                    )"
                    x-cloak
                >
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('system.support.*')"
                        icon="life-buoy"
                        heading="{{ __('general.support') }}"
                        class="grid"
                    >
                        @can('ticket_view')
                            <div x-show="match(@js(__('general.tickets')))" x-cloak>
                                <flux:sidebar.item
                                    icon="tickets"
                                    :href="route('system.support.tickets.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.support.tickets.*')"
                                    data-nav-label="{{ __('general.tickets') }}"
                                >
                                    {{ __('general.tickets') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan

                        @can('ticket_category_view')
                            <div x-show="match(@js(__('general.ticket_categories')))" x-cloak>
                                <flux:sidebar.item
                                    icon="folder-kanban"
                                    :href="route('system.support.ticket-categories.index')"
                                    wire:navigate
                                    :current="request()->routeIs('system.support.ticket-categories.*')"
                                    data-nav-label="{{ __('general.ticket_categories') }}"
                                >
                                    {{ __('general.ticket_categories') }}
                                </flux:sidebar.item>
                            </div>
                        @endcan
                    </flux:sidebar.group>
                </div>
            @endif

            @can('business_view')
                <div x-show="match(@js(__('general.businesses')))" x-cloak>
                    <flux:sidebar.item
                        icon="building"
                        :href="route('system.businesses.index')"
                        wire:navigate
                        :current="request()->routeIs('system.businesses.*')"
                        data-nav-label="{{ __('general.businesses') }}"
                    >
                        {{ __('general.businesses') }}
                    </flux:sidebar.item>
                </div>
            @endcan

            @can('currency_view')
                <div x-show="match(@js(__('general.currencies')))" x-cloak>
                    <flux:sidebar.item
                        icon="coins"
                        :href="route('system.currencies.index')"
                        wire:navigate
                        :current="request()->routeIs('system.currencies.*')"
                        data-nav-label="{{ __('general.currencies') }}"
                    >
                        {{ __('general.currencies') }}
                    </flux:sidebar.item>
                </div>
            @endcan

            @can('category_view')
                <div x-show="match(@js(__('general.system_categories')))" x-cloak>
                    <flux:sidebar.item
                        icon="folder-tree"
                        :href="route('system.categories.index')"
                        wire:navigate
                        :current="request()->routeIs('system.categories.*')"
                        data-nav-label="{{ __('general.system_categories') }}"
                    >
                        {{ __('general.system_categories') }}
                    </flux:sidebar.item>
                </div>
            @endcan
        @elseif (request()->routeIs('user.*'))
            <div x-show="match(@js(__('general.dashboard')))" x-cloak>
                <flux:sidebar.item
                    icon="home"
                    :href="route('user.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('user.dashboard')"
                    data-nav-label="{{ __('general.dashboard') }}"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            </div>

            <div x-show="match(@js(__('general.my_businesses')))" x-cloak>
                <flux:sidebar.item
                    icon="building"
                    :href="route('user.businesses.index')"
                    wire:navigate
                    :current="request()->routeIs('user.businesses.*')"
                    data-nav-label="{{ __('general.my_businesses') }}"
                >
                    {{ __('general.my_businesses') }}
                </flux:sidebar.item>
            </div>

            <div x-show="match(@js(__('general.support')))" x-cloak>
                <flux:sidebar.item
                    icon="life-buoy"
                    :href="route('user.support.tickets.index')"
                    wire:navigate
                    :current="request()->routeIs('user.support.*')"
                    data-nav-label="{{ __('general.support') }}"
                >
                    {{ __('general.support') }}
                </flux:sidebar.item>
            </div>
        @elseif (request()->routeIs('warehouse.*'))
            <div x-show="match(@js(__('general.dashboard')))" x-cloak>
                <flux:sidebar.item
                    icon="home"
                    :href="route('warehouse.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('warehouse.dashboard')"
                    data-nav-label="{{ __('general.dashboard') }}"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            </div>

            @if (auth()->user()?->canInBusiness(BusinessPermission::StockReceive) || auth()->user()?->canInBusiness(BusinessPermission::StockIssue) || auth()->user()?->canInBusiness(BusinessPermission::StockTransfer) || auth()->user()?->canInBusiness(BusinessPermission::StockAdjust) || auth()->user()?->canInBusiness(BusinessPermission::StockAllocate))
                <div
                    x-show="groupVisible(
                        @js(__('general.stock_operations')),
                        @js(__('general.stock_receive')),
                        @js(__('general.stock_issue')),
                        @js(__('general.stock_transfer')),
                        @js(__('general.stock_adjust')),
                        @js(__('general.stock_allocate'))
                    )"
                    x-cloak
                >
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('warehouse.stocks.*')"
                        icon="package-open"
                        heading="{{ __('general.stock_operations') }}"
                        class="grid"
                    >
                        @if (auth()->user()?->canInBusiness(BusinessPermission::StockMovementView))
                            <div x-show="match(@js(__('general.inventory_stocks')))" x-cloak>
                                <flux:sidebar.item
                                    :href="route('warehouse.stocks.index')"
                                    wire:navigate
                                    :current="request()->routeIs('warehouse.stocks.*')"
                                    data-nav-label="{{ __('general.inventory_stocks') }}"
                                >
                                    {{ __('general.inventory_stocks') }}
                                </flux:sidebar.item>
                            </div>
                        @endif
                    </flux:sidebar.group>
                </div>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::StockMovementView))
                <div
                    x-show="groupVisible(
                        @js(__('general.inventory')),
                        @js(__('general.inventory_stocks')),
                        @js(__('general.stock_movements')),
                        @js(__('general.stock_conversions'))
                    )"
                    x-cloak
                >
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('warehouse.stocks.*') || request()->routeIs('warehouse.movements.*') || request()->routeIs('warehouse.conversions.*')"
                        icon="boxes"
                        heading="{{ __('general.inventory') }}"
                        class="grid"
                    >
                        <div x-show="match(@js(__('general.inventory_stocks')))" x-cloak>
                            <flux:sidebar.item
                                :href="route('warehouse.stocks.index')"
                                wire:navigate
                                :current="request()->routeIs('warehouse.stocks.*')"
                                data-nav-label="{{ __('general.inventory_stocks') }}"
                            >
                                {{ __('general.inventory_stocks') }}
                            </flux:sidebar.item>
                        </div>
                        <div x-show="match(@js(__('general.stock_movements')))" x-cloak>
                            <flux:sidebar.item
                                :href="route('warehouse.movements.index')"
                                wire:navigate
                                :current="request()->routeIs('warehouse.movements.*')"
                                data-nav-label="{{ __('general.stock_movements') }}"
                            >
                                {{ __('general.stock_movements') }}
                            </flux:sidebar.item>
                        </div>
                        <div x-show="match(@js(__('general.stock_conversions')))" x-cloak>
                            <flux:sidebar.item
                                :href="route('warehouse.conversions.index')"
                                wire:navigate
                                :current="request()->routeIs('warehouse.conversions.*')"
                                data-nav-label="{{ __('general.stock_conversions') }}"
                            >
                                {{ __('general.stock_conversions') }}
                            </flux:sidebar.item>
                        </div>
                    </flux:sidebar.group>
                </div>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::WarehouseView))
                <div x-show="match(@js(__('general.warehouses')))" x-cloak>
                    <flux:sidebar.item
                        icon="warehouse"
                        :href="route('warehouse.warehouses.index')"
                        wire:navigate
                        :current="request()->routeIs('warehouse.warehouses.*')"
                        data-nav-label="{{ __('general.warehouses') }}"
                    >
                        {{ __('general.warehouses') }}
                    </flux:sidebar.item>
                </div>
            @endif
        @elseif (request()->routeIs('sale.*'))
            <div x-show="match(@js(__('general.dashboard')))" x-cloak>
                <flux:sidebar.item
                    icon="home"
                    :href="route('sale.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('sale.dashboard')"
                    data-nav-label="{{ __('general.dashboard') }}"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            </div>

            @if (auth()->user()?->canInBusiness(BusinessPermission::InvoiceView))
                <div x-show="match(@js(__('general.invoices')))" x-cloak>
                    <flux:sidebar.item
                        icon="file-text"
                        :href="route('sale.invoices.index')"
                        wire:navigate
                        :current="request()->routeIs('sale.invoices.*')"
                        data-nav-label="{{ __('general.invoices') }}"
                    >
                        {{ __('general.invoices') }}
                    </flux:sidebar.item>
                </div>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::QuoteView))
                <div x-show="match(@js(__('general.quotes')))" x-cloak>
                    <flux:sidebar.item
                        icon="file-pen-line"
                        :href="route('sale.quotes.index')"
                        wire:navigate
                        :current="request()->routeIs('sale.quotes.*')"
                        data-nav-label="{{ __('general.quotes') }}"
                    >
                        {{ __('general.quotes') }}
                    </flux:sidebar.item>
                </div>
            @endif

            @if (auth()->user()?->canInBusiness(BusinessPermission::PartyView))
                <div x-show="match(@js(__('general.parties')))" x-cloak>
                    <flux:sidebar.item
                        icon="users"
                        :href="route('sale.parties.index')"
                        wire:navigate
                        :current="request()->routeIs('sale.parties.*')"
                        data-nav-label="{{ __('general.parties') }}"
                    >
                        {{ __('general.parties') }}
                    </flux:sidebar.item>
                </div>
            @endif
        @elseif (request()->routeIs('accounting.*'))
            <div x-show="match(@js(__('general.dashboard')))" x-cloak>
                <flux:sidebar.item
                    icon="home"
                    :href="route('accounting.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('accounting.dashboard')"
                    data-nav-label="{{ __('general.dashboard') }}"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            </div>

            <div
                x-show="groupVisible(
                    @js(__('general.general_ledger')),
                    @js(__('general.journal_entries')),
                    @js(__('general.fiscal_years')),
                    @js(__('general.cost_centers')),
                    @js(__('general.projects'))
                )"
                x-cloak
            >
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('accounting.journal-entries.*') || request()->routeIs('accounting.fiscal-years.*') || request()->routeIs('accounting.cost-centers.*') || request()->routeIs('accounting.projects.*')"
                    icon="book-open"
                    heading="{{ __('general.general_ledger') }}"
                    class="grid"
                >
                    <div x-show="match(@js(__('general.journal_entries')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.journal-entries.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.journal-entries.*')"
                            data-nav-label="{{ __('general.journal_entries') }}"
                        >
                            {{ __('general.journal_entries') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.fiscal_years')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.fiscal-years.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.fiscal-years.*')"
                            data-nav-label="{{ __('general.fiscal_years') }}"
                        >
                            {{ __('general.fiscal_years') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.cost_centers')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.cost-centers.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.cost-centers.*')"
                            data-nav-label="{{ __('general.cost_centers') }}"
                        >
                            {{ __('general.cost_centers') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.projects')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.projects.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.projects.*')"
                            data-nav-label="{{ __('general.projects') }}"
                        >
                            {{ __('general.projects') }}
                        </flux:sidebar.item>
                    </div>
                </flux:sidebar.group>
            </div>

            <div
                x-show="groupVisible(
                    @js(__('general.treasury')),
                    @js(__('general.cash_and_bank_accounts')),
                    @js(__('general.categories')),
                    @js(__('general.business_currencies')),
                    @js(__('general.sale_kinds')),
                    @js(__('general.settlement_types'))
                )"
                x-cloak
            >
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('accounting.accounts.*') || request()->routeIs('accounting.currencies.*') || request()->routeIs('accounting.categories.*') || request()->routeIs('accounting.sale-kinds.*') || request()->routeIs('accounting.settlement-types.*')"
                    icon="wallet"
                    heading="{{ __('general.treasury') }}"
                    class="grid"
                >
                    <div x-show="match(@js(__('general.cash_and_bank_accounts')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.accounts.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.accounts.*')"
                            data-nav-label="{{ __('general.cash_and_bank_accounts') }}"
                        >
                            {{ __('general.cash_and_bank_accounts') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.categories')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.categories.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.categories.*')"
                            data-nav-label="{{ __('general.categories') }}"
                        >
                            {{ __('general.categories') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.business_currencies')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.currencies.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.currencies.*')"
                            data-nav-label="{{ __('general.business_currencies') }}"
                        >
                            {{ __('general.business_currencies') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.sale_kinds')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.sale-kinds.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.sale-kinds.*')"
                            data-nav-label="{{ __('general.sale_kinds') }}"
                        >
                            {{ __('general.sale_kinds') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.settlement_types')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.settlement-types.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.settlement-types.*')"
                            data-nav-label="{{ __('general.settlement_types') }}"
                        >
                            {{ __('general.settlement_types') }}
                        </flux:sidebar.item>
                    </div>
                </flux:sidebar.group>
            </div>

            <div
                x-show="groupVisible(
                    @js(__('general.catalog')),
                    @js(__('general.products')),
                    @js(__('general.categories')),
                    @js(__('general.brands'))
                )"
                x-cloak
            >
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('accounting.catalog.*')"
                    icon="package"
                    heading="{{ __('general.catalog') }}"
                    class="grid"
                >
                    <div x-show="match(@js(__('general.products')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.catalog.products.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.catalog.products.*')"
                            data-nav-label="{{ __('general.products') }}"
                        >
                            {{ __('general.products') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.categories')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.catalog.categories.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.catalog.categories.*')"
                            data-nav-label="{{ __('general.categories') }}"
                        >
                            {{ __('general.categories') }}
                        </flux:sidebar.item>
                    </div>

                    <div x-show="match(@js(__('general.brands')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.catalog.brands.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.catalog.brands.*')"
                            data-nav-label="{{ __('general.brands') }}"
                        >
                            {{ __('general.brands') }}
                        </flux:sidebar.item>
                    </div>
                </flux:sidebar.group>
            </div>

            <div x-show="match(@js(__('general.invoices')))" x-cloak>
                <flux:sidebar.item
                    icon="file-text"
                    :href="route('accounting.invoices.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.invoices.*')"
                    data-nav-label="{{ __('general.invoices') }}"
                >
                    {{ __('general.invoices') }}
                </flux:sidebar.item>
            </div>

            <div x-show="match(@js(__('general.quotes')))" x-cloak>
                <flux:sidebar.item
                    icon="file-pen-line"
                    :href="route('accounting.quotes.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.quotes.*')"
                    data-nav-label="{{ __('general.quotes') }}"
                >
                    {{ __('general.quotes') }}
                </flux:sidebar.item>
            </div>

            <div x-show="match(@js(__('general.transactions')))" x-cloak>
                <flux:sidebar.item
                    icon="arrow-left-right"
                    :href="route('accounting.transactions.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.transactions.*')"
                    data-nav-label="{{ __('general.transactions') }}"
                >
                    {{ __('general.transactions') }}
                </flux:sidebar.item>
            </div>

            <div x-show="match(@js(__('general.debts_and_loans')))" x-cloak>
                <flux:sidebar.item
                    icon="hand-coins"
                    :href="route('accounting.loans.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.loans.*')"
                    data-nav-label="{{ __('general.debts_and_loans') }}"
                >
                    {{ __('general.debts_and_loans') }}
                </flux:sidebar.item>
            </div>

            <div x-show="match(@js(__('general.cheques')))" x-cloak>
                <flux:sidebar.item
                    icon="banknote"
                    :href="route('accounting.cheques.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.cheques.*')"
                    data-nav-label="{{ __('general.cheques') }}"
                >
                    {{ __('general.cheques') }}
                </flux:sidebar.item>
            </div>

            <div
                x-show="groupVisible(@js(__('general.parties')), @js(__('general.parties')))"
                x-cloak
            >
                <flux:sidebar.group
                    expandable
                    :expanded="request()->routeIs('accounting.parties.*')"
                    icon="users"
                    heading="{{ __('general.parties') }}"
                    class="grid"
                >
                    <div x-show="match(@js(__('general.parties')))" x-cloak>
                        <flux:sidebar.item
                            :href="route('accounting.parties.index')"
                            wire:navigate
                            :current="request()->routeIs('accounting.parties.*')"
                            data-nav-label="{{ __('general.parties') }}"
                        >
                            {{ __('general.parties') }}
                        </flux:sidebar.item>
                    </div>
                </flux:sidebar.group>
            </div>
        @endif
    </flux:sidebar.nav>
</div>
