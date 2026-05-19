<?php

declare(strict_types=1);

namespace MadaraCoder\LaravelEsewa\Tests;

use MadaraCoder\LaravelEsewa\LaravelEsewaServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelEsewaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('esewa.scd', 'EPAYTEST');
        $app['config']->set('esewa.secret_key', '8gBm/:&EnhH.1/q');
        $app['config']->set('esewa.env', 'Sandbox');
    }
}
