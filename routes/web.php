<?php

use App\Http\Controllers\Support\TicketFileController;
use App\Models\ShortLink;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['en', 'fa'], true)) {
        abort(404);
    }

    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.switch');

Route::get('/i/{code}', function (string $code) {
    $shortLink = ShortLink::query()->where('code', $code)->firstOrFail();

    abort_if($shortLink->isExpired(), 404);

    $shortLink->increment('hits');

    return redirect()->away($shortLink->destination);
})->name('short-links.redirect');

Route::livewire('/invoices/public/{invoice}', 'pages::public.invoice.show')
    ->middleware('signed')
    ->name('invoices.public');

Route::livewire('/quotes/public/{quote}', 'pages::public.quote.show')
    ->middleware('signed')
    ->name('quotes.public');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/user')->name('dashboard');

    Route::prefix('system')->name('system.')->group(function () {
        Route::livewire('/', 'pages::panel.administrator.dashboard.index')
            ->middleware('permission:dashboard_view')
            ->name('dashboard');

        Route::livewire('/users', 'pages::panel.administrator.user-management.user.index')
            ->middleware('permission:user_view')
            ->name('users.index');
        Route::livewire('/users/{user}/access', 'pages::panel.administrator.user-management.user.access')
            ->middleware('permission:user_access')
            ->name('users.access');

        Route::livewire('/roles', 'pages::panel.administrator.user-management.role.index')
            ->middleware('permission:role_view')
            ->name('roles.index');
        Route::livewire('/roles/{role}/access', 'pages::panel.administrator.user-management.role.access')
            ->middleware('permission:role_access')
            ->name('roles.access');

        Route::livewire('/permissions', 'pages::panel.administrator.user-management.permission.index')
            ->middleware('permission:permission_view')
            ->name('permissions.index');
        Route::livewire('/permissions/{permission}/users', 'pages::panel.administrator.user-management.permission.users')
            ->middleware('permission:permission_access')
            ->name('permissions.users');

        Route::livewire('/businesses', 'pages::panel.administrator.business.index')
            ->middleware('permission:business_view')
            ->name('businesses.index');
        Route::livewire('/businesses/{business}/users', 'pages::panel.administrator.business.users')
            ->middleware('permission:business_user_manage')
            ->name('businesses.users');

        Route::livewire('/currencies', 'pages::panel.administrator.currency.index')
            ->middleware('permission:currency_view')
            ->name('currencies.index');

        Route::livewire('/categories', 'pages::panel.administrator.category.index')
            ->middleware('permission:category_view')
            ->name('categories.index');

        Route::livewire('/support/tickets', 'pages::panel.administrator.support.ticket.index')
            ->middleware('permission:ticket_view')
            ->name('support.tickets.index');
        Route::livewire('/support/tickets/{ticket}', 'pages::panel.administrator.support.ticket.view')
            ->middleware('permission:ticket_view')
            ->name('support.tickets.view');
        Route::livewire('/support/ticket-categories', 'pages::panel.administrator.support.ticket.category.index')
            ->middleware('permission:ticket_category_view')
            ->name('support.ticket-categories.index');

        Route::livewire('/functions', 'pages::panel.administrator.system-management.function.index')
            ->middleware('permission:function_view')
            ->name('functions.index');

        Route::livewire('/backups', 'pages::panel.administrator.system-management.backup.index')
            ->middleware('permission:backup_view')
            ->name('backups.index');

        Route::livewire('/settings', 'pages::panel.administrator.system-management.setting.index')
            ->middleware('permission:setting_view')
            ->name('settings.index');
    });

    Route::prefix('user')->name('user.')->group(function () {
        Route::livewire('/businesses/create', 'pages::panel.user.business.create')->name('businesses.create');

        Route::livewire('/support/tickets', 'pages::panel.user.support.ticket.index')
            ->name('support.tickets.index');
        Route::livewire('/support/tickets/create', 'pages::panel.user.support.ticket.create')
            ->name('support.tickets.create');
        Route::livewire('/support/tickets/{ticket}', 'pages::panel.user.support.ticket.view')
            ->name('support.tickets.view');

        Route::middleware('business.selected')->group(function () {
            Route::livewire('/', 'pages::panel.user.dashboard.index')->name('dashboard');
            Route::livewire('/businesses', 'pages::panel.user.business.index')->name('businesses.index');
            Route::livewire('/businesses/{business}/users', 'pages::panel.user.business.users')->name('businesses.users');
            Route::livewire('/businesses/{business}', 'pages::panel.user.business.view')->name('businesses.view');
            Route::livewire('/businesses/{business}/edit', 'pages::panel.user.business.edit')->name('businesses.edit');
            Route::livewire('/businesses/{business}/invoice-preview', 'pages::panel.user.business.invoice-preview')
                ->name('businesses.invoice-preview');
            Route::livewire('/businesses/{business}/quote-preview', 'pages::panel.user.business.quote-preview')
                ->name('businesses.quote-preview');
        });
    });

    Route::get('/support/ticket-files/{ticketFile}', [TicketFileController::class, 'show'])
        ->name('support.ticket-files.show');

    Route::prefix('accounting')->name('accounting.')->middleware(['business.selected', 'business.panel:accounting'])->group(function () {
        Route::livewire('/', 'pages::panel.accounting.dashboard.index')->name('dashboard');
        Route::livewire('/accounts', 'pages::panel.accounting.account.index')->name('accounts.index');
        Route::livewire('/accounts/{account}', 'pages::panel.accounting.account.view')->name('accounts.view');
        Route::livewire('/parties', 'pages::panel.accounting.party.index')->name('parties.index');
        Route::livewire('/parties/{party}', 'pages::panel.accounting.party.view')->name('parties.view');
        Route::livewire('/catalog/products', 'pages::panel.accounting.catalog.product.index')->name('catalog.products.index');
        Route::livewire('/catalog/categories', 'pages::panel.accounting.category.index')->name('catalog.categories.index');
        Route::livewire('/catalog/brands', 'pages::panel.accounting.catalog.brand.index')->name('catalog.brands.index');
        Route::livewire('/categories', 'pages::panel.accounting.category.index')->name('categories.index');
        Route::livewire('/invoices', 'pages::panel.accounting.invoice.index')->name('invoices.index');
        Route::livewire('/invoices/create/{type}', 'pages::panel.accounting.invoice.form')->name('invoices.create');
        Route::livewire('/invoices/{invoice}/edit', 'pages::panel.accounting.invoice.form')->name('invoices.edit');
        Route::livewire('/quotes', 'pages::panel.accounting.quote.index')->name('quotes.index');
        Route::livewire('/quotes/create', 'pages::panel.accounting.quote.form')->name('quotes.create');
        Route::livewire('/quotes/{quote}/edit', 'pages::panel.accounting.quote.form')->name('quotes.edit');
        Route::livewire('/transactions', 'pages::panel.accounting.transaction.index')->name('transactions.index');
        Route::livewire('/loans', 'pages::panel.accounting.loan.index')->name('loans.index');
        Route::livewire('/loans/{loan}', 'pages::panel.accounting.loan.show')->name('loans.show');
        Route::livewire('/cheques', 'pages::panel.accounting.cheque.index')->name('cheques.index');
        Route::livewire('/currencies', 'pages::panel.accounting.currency.index')->name('currencies.index');
        Route::livewire('/sale-kinds', 'pages::panel.accounting.sale-kind.index')->name('sale-kinds.index');
        Route::livewire('/settlement-types', 'pages::panel.accounting.settlement-type.index')->name('settlement-types.index');

        Route::livewire('/journal-entries', 'pages::panel.accounting.journal-entry.index')->name('journal-entries.index');
        Route::livewire('/journal-entries/create', 'pages::panel.accounting.journal-entry.form')->name('journal-entries.create');
        Route::livewire('/journal-entries/{journalEntry}/edit', 'pages::panel.accounting.journal-entry.form')->name('journal-entries.edit');
        Route::livewire('/fiscal-years', 'pages::panel.accounting.fiscal-year.index')->name('fiscal-years.index');
        Route::livewire('/cost-centers', 'pages::panel.accounting.cost-center.index')->name('cost-centers.index');
        Route::livewire('/projects', 'pages::panel.accounting.project.index')->name('projects.index');
    });

    Route::prefix('warehouse')->name('warehouse.')->middleware(['business.selected', 'business.panel:warehouse'])->group(function () {
        Route::livewire('/', 'pages::panel.warehouse.dashboard.index')->name('dashboard');

        Route::livewire('/warehouses', 'pages::panel.warehouse.warehouse.index')
            ->middleware('business.permission:warehouse_view')
            ->name('warehouses.index');

        Route::livewire('/stocks', 'pages::panel.warehouse.stock.index')
            ->middleware('business.permission:stock_movement_view')
            ->name('stocks.index');

        Route::livewire('/movements', 'pages::panel.warehouse.movement.index')
            ->middleware('business.permission:stock_movement_view')
            ->name('movements.index');

        Route::livewire('/conversions', 'pages::panel.warehouse.conversion.index')
            ->middleware('business.permission:stock_movement_view')
            ->name('conversions.index');
    });

    Route::prefix('sale')->name('sale.')->middleware(['business.selected', 'business.panel:sale'])->group(function () {
        Route::livewire('/', 'pages::panel.sale.dashboard.index')->name('dashboard');

        Route::livewire('/invoices', 'pages::panel.accounting.invoice.index')
            ->middleware('business.permission:invoice_view')
            ->name('invoices.index');
        Route::livewire('/invoices/create/{type}', 'pages::panel.accounting.invoice.form')
            ->middleware('business.permission:invoice_create')
            ->name('invoices.create');
        Route::livewire('/invoices/{invoice}/edit', 'pages::panel.accounting.invoice.form')
            ->middleware('business.permission:invoice_edit')
            ->name('invoices.edit');

        Route::livewire('/quotes', 'pages::panel.accounting.quote.index')
            ->middleware('business.permission:quote_view')
            ->name('quotes.index');
        Route::livewire('/quotes/create', 'pages::panel.accounting.quote.form')
            ->middleware('business.permission:quote_create')
            ->name('quotes.create');
        Route::livewire('/quotes/{quote}/edit', 'pages::panel.accounting.quote.form')
            ->middleware('business.permission:quote_edit')
            ->name('quotes.edit');

        Route::livewire('/parties', 'pages::panel.accounting.party.index')
            ->middleware('business.permission:party_view')
            ->name('parties.index');
        Route::livewire('/parties/{party}', 'pages::panel.accounting.party.view')
            ->middleware('business.permission:party_view')
            ->name('parties.view');
    });
});
