<?php

declare(strict_types=1);

namespace MadaraCoder\LaravelEsewa;

use Illuminate\Support\ServiceProvider;

class LaravelEsewaServiceProvider extends ServiceProvider
{
    /**
     * Register package services into the Laravel service container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/esewa.php',
            'esewa'
        );

        $this->app->singleton('laravel-esewa', function () {
            return new LaravelEsewa();
        });
    }

    /**
     * Bootstrap package services.
     *
     * Allows users to publish the config file with:
     * php artisan vendor:publish --tag=esewa-config
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/esewa.php' => config_path('esewa.php'),
        ], 'esewa-config');
    }
}
