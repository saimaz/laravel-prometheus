<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis as LaravelRedis;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;
use Ninebit\LaravelPrometheus\Collectors\HorizonCollector;
use Ninebit\LaravelPrometheus\Contracts\HttpLabelProvider;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;
use Ninebit\LaravelPrometheus\Middleware\HttpMetricsMiddleware;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

class LaravelPrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/prometheus.php', 'prometheus');

        $this->app->singleton(Adapter::class, fn () => $this->buildStorageAdapter());

        $this->app->singleton(CollectorRegistry::class, fn () => new CollectorRegistry(
            $this->app->make(Adapter::class),
            false,
        ));

        $this->app->singleton(MetricsRegistryInterface::class, MetricsRegistry::class);
        $this->app->singleton(MetricsRegistry::class);

        $this->registerHttpLabelProvider();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/prometheus.php' => config_path('prometheus.php'),
            ], 'prometheus-config');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/metrics.php');
        $this->registerHttpMiddleware();
        $this->autoRegisterCollectors();
    }

    private function registerHttpMiddleware(): void
    {
        if (! config('prometheus.http.enabled', true)) {
            return;
        }

        if ($this->app->bound(Kernel::class)) {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(HttpMetricsMiddleware::class);
        }
    }

    private function autoRegisterCollectors(): void
    {
        // Auto-detect Horizon and register its collector
        if (class_exists(Horizon::class) && ! in_array(HorizonCollector::class, config('prometheus.collectors', []))) {
            $collectors = config('prometheus.collectors', []);
            $collectors[] = HorizonCollector::class;
            config()->set('prometheus.collectors', $collectors);
        }
    }

    private function buildStorageAdapter(): Adapter
    {
        if (! config('prometheus.enabled') || $this->app->environment('testing')) {
            return new InMemory;
        }

        $driver = config('prometheus.storage.driver', 'redis');

        return match ($driver) {
            'redis' => $this->buildRedisAdapter(),
            'apc' => new APC,
            default => new InMemory,
        };
    }

    private function buildRedisAdapter(): Adapter
    {
        $connection = config('prometheus.storage.redis.connection', 'default');

        try {
            Redis::setPrefix(config('prometheus.storage.redis.prefix', 'PROMETHEUS_'));

            return Redis::fromExistingConnection(
                LaravelRedis::connection($connection)->client(),
            );
        } catch (\Throwable $e) {
            Log::error('Prometheus: Redis connection failed, falling back to in-memory storage', [
                'connection' => $connection,
                'error' => $e->getMessage(),
            ]);

            return new InMemory;
        }
    }

    private function registerHttpLabelProvider(): void
    {
        $provider = config('prometheus.http.label_provider');

        if ($provider && class_exists($provider)) {
            $this->app->singleton(HttpLabelProvider::class, $provider);
        }
    }
}
