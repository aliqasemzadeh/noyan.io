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
        Route::livewire('/users', 'pages::user.index')->name('users.index');
    });

    Route::prefix('user')->name('user.')->group(function () {
        Route::livewire('/', 'pages::panel.user.dashboard.index')->name('dashboard');
    });

    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::livewire('/', 'pages::panel.accounting.dashboard.index')->name('dashboard');
    });
});
