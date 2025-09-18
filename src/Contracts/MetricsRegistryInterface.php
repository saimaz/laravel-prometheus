<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Contracts;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Summary;

interface MetricsRegistryInterface
{
    public function counter(MetricDefinition $metric): Counter;

    public function gauge(MetricDefinition $metric): Gauge;

    public function histogram(MetricDefinition $metric, ?array $buckets = null): Histogram;

    public function summary(MetricDefinition $metric, int $maxAgeSeconds = 600, ?array $quantiles = null): Summary;

    public function observeDuration(MetricDefinition $metric, int $startTime, array $labels): void;

    public function collectFamilySamples(): array;
}
