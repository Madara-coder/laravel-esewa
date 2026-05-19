<?php

namespace MadaraCoder\LaravelEsewa;

use Illuminate\Support\ServiceProvider;

class LaravelEsewaServiceProvider extends ServiceProvider
{
    /**
     * Register package services into the container.
     */
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__ . '/config/esewa.php',
            'esewa'
        );

        // Bind as a singleton so only one instance is created per request
        $this->app->singleton('laravel-esewa', function () {
            return new LaravelEsewa();
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Allow users to publish config file with:
        // php artisan vendor:publish --tag=esewa-config
        $this->publishes([
            __DIR__ . '/config/esewa.php' => config_path('esewa.php'),
        ], 'esewa-config');
    }
}
