<?php

namespace MadaraCoder\LaravelEsewa\Tests;

use MadaraCoder\LaravelEsewa\LaravelEsewaServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Load the package service provider so config() and the
     * service container bindings are available in all tests.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelEsewaServiceProvider::class,
        ];
    }

    /**
     * Set sensible default config values for every test.
     * Individual tests can override these as needed.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('esewa.scd', 'EPAYTEST');
        $app['config']->set('esewa.env', 'Sandbox');
    }
}
