<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus;

use Ninebit\LaravelPrometheus\Contracts\MetricDefinition;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Summary;

readonly class MetricsRegistry implements MetricsRegistryInterface
{
    public function __construct(
        private CollectorRegistry $registry,
    ) {}

    public function counter(MetricDefinition $metric): Counter
    {
        return $this->registry->getOrRegisterCounter(
            $this->namespace(),
            $this->name($metric),
            $metric->helpText(),
            $metric->labelNames(),
        );
    }

    public function gauge(MetricDefinition $metric): Gauge
    {
        return $this->registry->getOrRegisterGauge(
            $this->namespace(),
            $this->name($metric),
            $metric->helpText(),
            $metric->labelNames(),
        );
    }

    public function histogram(MetricDefinition $metric, ?array $buckets = null): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            $this->namespace(),
            $this->name($metric),
            $metric->helpText(),
            $metric->labelNames(),
            $buckets ?? $metric->buckets(),
        );
    }

    public function summary(
        MetricDefinition $metric,
        int $maxAgeSeconds = 600,
        ?array $quantiles = null,
    ): Summary {
        return $this->registry->getOrRegisterSummary(
            $this->namespace(),
            $this->name($metric),
            $metric->helpText(),
            $metric->labelNames(),
            $maxAgeSeconds,
            $quantiles,
        );
    }

    public function observeDuration(MetricDefinition $metric, int $startTime, array $labels): void
    {
        $labels = array_pad($labels, count($metric->labelNames()), '');
        $this->histogram($metric)->observe((hrtime(true) - $startTime) / 1e9, $labels);
    }

    public function collectFamilySamples(): array
    {
        return $this->registry->getMetricFamilySamples();
    }

    private function namespace(): string
    {
        return config('prometheus.default_namespace', 'app');
    }

    private function name(MetricDefinition $metric): string
    {
        if ($metric instanceof \BackedEnum) {
            return $metric->value;
        }

        return (string) $metric;
    }
}
