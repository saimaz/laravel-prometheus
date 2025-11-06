<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Tests;

use Ninebit\LaravelPrometheus\LaravelPrometheusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelPrometheusServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('prometheus.enabled', true);
        $app['config']->set('prometheus.storage.driver', 'in_memory');
    }
}
