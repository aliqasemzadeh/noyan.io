<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div>
    <flux:sidebar.nav>
        @if (request()->routeIs('system.*'))
            @can('dashboard_view')
                <flux:sidebar.item
                    icon="home"
                    :href="route('system.dashboard')"
                    wire:navigate
                    :current="request()->routeIs('system.dashboard')"
                >
                    {{ __('general.dashboard') }}
                </flux:sidebar.item>
            @endcan

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
                        >
                            {{ __('general.system_settings') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endif

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
                    >
                        {{ __('general.users') }}
                    </flux:sidebar.item>
                @endcan

                @can('role_view')
                    <flux:sidebar.item
                        :href="route('system.roles.index')"
                        wire:navigate
                        :current="request()->routeIs('system.roles.*')"
                    >
                        {{ __('general.roles') }}
                    </flux:sidebar.item>
                @endcan

                @can('permission_view')
                    <flux:sidebar.item
                        :href="route('system.permissions.index')"
                        wire:navigate
                        :current="request()->routeIs('system.permissions.*')"
                    >
                        {{ __('general.permissions') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>

            @can('business_view')
                <flux:sidebar.item
                    icon="building"
                    :href="route('system.businesses.index')"
                    wire:navigate
                    :current="request()->routeIs('system.businesses.*')"
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
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="building"
                :href="route('user.businesses.index')"
                wire:navigate
                :current="request()->routeIs('user.businesses.*')"
            >
                {{ __('general.my_businesses') }}
            </flux:sidebar.item>
        @elseif (request()->routeIs('accounting.*'))
            <flux:sidebar.item
                icon="home"
                :href="route('accounting.dashboard')"
                wire:navigate
                :current="request()->routeIs('accounting.dashboard')"
            >
                {{ __('general.dashboard') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="file-text"
                :href="route('accounting.invoices.index')"
                wire:navigate
                :current="request()->routeIs('accounting.invoices.*')"
            >
                {{ __('general.invoices') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="arrow-left-right"
                :href="route('accounting.transactions.index')"
                wire:navigate
                :current="request()->routeIs('accounting.transactions.*')"
            >
                {{ __('general.transactions') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="hand-coins"
                :href="route('accounting.loans.index')"
                wire:navigate
                :current="request()->routeIs('accounting.loans.*')"
            >
                {{ __('general.debts_and_loans') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="banknote"
                :href="route('accounting.cheques.index')"
                wire:navigate
                :current="request()->routeIs('accounting.cheques.*')"
            >
                {{ __('general.cheques') }}
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
                >
                    {{ __('general.journal_entries') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.fiscal-years.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.fiscal-years.*')"
                >
                    {{ __('general.fiscal_years') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.cost-centers.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.cost-centers.*')"
                >
                    {{ __('general.cost_centers') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.projects.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.projects.*')"
                >
                    {{ __('general.projects') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

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
                >
                    {{ __('general.parties') }}
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
                >
                    {{ __('general.products') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.catalog.categories.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.categories.*')"
                >
                    {{ __('general.categories') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.catalog.brands.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.catalog.brands.*')"
                >
                    {{ __('general.brands') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group
                expandable
                :expanded="request()->routeIs('accounting.accounts.*') || request()->routeIs('accounting.currencies.*') || request()->routeIs('accounting.categories.*')"
                icon="wallet"
                heading="{{ __('general.treasury') }}"
                class="grid"
            >
                <flux:sidebar.item
                    :href="route('accounting.accounts.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.accounts.*')"
                >
                    {{ __('general.cash_and_bank_accounts') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.categories.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.categories.*')"
                >
                    {{ __('general.categories') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    :href="route('accounting.currencies.index')"
                    wire:navigate
                    :current="request()->routeIs('accounting.currencies.*')"
                >
                    {{ __('general.business_currencies') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        @endif
    </flux:sidebar.nav>
</div>
