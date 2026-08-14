<?php

namespace App\Providers;

use App\Support\AnalyticsCache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app', function ($view) {
            $view->with('bellAlerts', AnalyticsCache::bellAlerts(auth()->id()));
        });
    }
}
