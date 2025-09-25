<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Facades;

use Illuminate\Support\Facades\Facade;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;
use Ninebit\LaravelPrometheus\MetricsRegistry;

/**
 * @method static \Prometheus\Counter counter(\Ninebit\LaravelPrometheus\Contracts\MetricDefinition $metric)
 * @method static \Prometheus\Gauge gauge(\Ninebit\LaravelPrometheus\Contracts\MetricDefinition $metric)
 * @method static \Prometheus\Histogram histogram(\Ninebit\LaravelPrometheus\Contracts\MetricDefinition $metric)
 * @method static \Prometheus\Summary summary(\Ninebit\LaravelPrometheus\Contracts\MetricDefinition $metric, int $maxAgeSeconds = 600, ?array $quantiles = null)
 * @method static void observeDuration(\Ninebit\LaravelPrometheus\Contracts\MetricDefinition $metric, int $startTime, array $labels)
 * @method static array collectFamilySamples()
 *
 * @see MetricsRegistry
 */
class Prometheus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetricsRegistryInterface::class;
    }
}
