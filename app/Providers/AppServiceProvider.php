<?php

namespace App\Providers;

use App\Settings\GeneralSettings;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blaze::optimize()
            ->in(resource_path('views/components'));

        try {
            $settings = app(GeneralSettings::class);

            if (filled($settings->site_name)) {
                config(['app.name' => $settings->site_name]);
            }
        } catch (Throwable) {
            // Settings table may not be ready during migrate/boot.
        }
    }
}
