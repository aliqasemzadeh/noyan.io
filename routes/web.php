<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/system')->name('dashboard');

    Route::prefix('system')->name('system.')->group(function () {
        Route::livewire('/', 'pages::panel.administrator.dashboard.index')->name('dashboard');
        Route::livewire('/users', 'pages::panel.administrator.user.index')->name('users.index');
        Route::livewire('/businesses', 'pages::panel.administrator.business.index')->name('businesses.index');
        Route::livewire('/businesses/{business}/users', 'pages::panel.administrator.business.users')->name('businesses.users');
        Route::livewire('/currencies', 'pages::panel.administrator.currency.index')->name('currencies.index');
    });

    Route::prefix('user')->name('user.')->group(function () {
        Route::livewire('/businesses/create', 'pages::panel.user.business.create')->name('businesses.create');

        Route::middleware('business.selected')->group(function () {
            Route::livewire('/', 'pages::panel.user.dashboard.index')->name('dashboard');
            Route::livewire('/businesses', 'pages::panel.user.business.index')->name('businesses.index');
            Route::livewire('/businesses/{business}', 'pages::panel.user.business.view')->name('businesses.view');
            Route::livewire('/businesses/{business}/edit', 'pages::panel.user.business.edit')->name('businesses.edit');
        });
    });

    Route::prefix('accounting')->name('accounting.')->middleware('business.selected')->group(function () {
        Route::livewire('/', 'pages::panel.accounting.dashboard.index')->name('dashboard');
        Route::livewire('/accounts', 'pages::panel.accounting.account.index')->name('accounts.index');
        Route::livewire('/accounts/{account}', 'pages::panel.accounting.account.view')->name('accounts.view');
        Route::livewire('/parties', 'pages::panel.accounting.party.index')->name('parties.index');
        Route::livewire('/parties/{party}', 'pages::panel.accounting.party.view')->name('parties.view');
        Route::livewire('/currencies', 'pages::panel.accounting.currency.index')->name('currencies.index');
    });
});
