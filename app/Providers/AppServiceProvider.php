<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton('audit', function () {
            return new \App\Helpers\AuditHelper();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (env('ENFORCE_SSL', false)) {
            \URL::forceScheme('https');
        }
    }
}
