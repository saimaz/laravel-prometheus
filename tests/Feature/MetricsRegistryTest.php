<?php

declare(strict_types=1);

use Ninebit\LaravelPrometheus\BuiltInMetric;
use Ninebit\LaravelPrometheus\Contracts\MetricDefinition;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;

it('registers and increments a counter', function () {
    $registry = app(MetricsRegistryInterface::class);

    $counter = $registry->counter(BuiltInMetric::HTTP_REQUESTS);
    $counter->incBy(5, ['test-route', 'GET', '200']);

    $samples = $registry->collectFamilySamples();
    $metricNames = array_map(fn ($family) => $family->getName(), $samples);

    expect($metricNames)->toContain('app_http_requests_total');
});

it('registers and sets a gauge', function () {
    $registry = app(MetricsRegistryInterface::class);

    $gauge = $registry->gauge(BuiltInMetric::HORIZON_STATUS);
    $gauge->set(1);

    $samples = $registry->collectFamilySamples();
    $metricNames = array_map(fn ($family) => $family->getName(), $samples);

    expect($metricNames)->toContain('app_horizon_status');
});

it('registers a histogram with custom buckets', function () {
    $registry = app(MetricsRegistryInterface::class);

    $buckets = [0.1, 0.5, 1.0, 5.0];
    $histogram = $registry->histogram(BuiltInMetric::HTTP_REQUEST_DURATION, $buckets);
    $histogram->observe(0.35, ['test-route', 'GET', '200']);

    $samples = $registry->collectFamilySamples();
    $metricNames = array_map(fn ($family) => $family->getName(), $samples);

    expect($metricNames)->toContain('app_http_request_duration_seconds');
});

it('works with custom metric definition enums', function () {
    $customMetric = new class implements MetricDefinition
    {
        public function helpText(): string
        {
            return 'Custom test metric';
        }

        public function labelNames(): array
        {
            return ['label_a'];
        }

        public function buckets(): ?array
        {
            return null;
        }

        public function __toString(): string
        {
            return 'custom_test_metric';
        }
    };

    $registry = app(MetricsRegistryInterface::class);
    $counter = $registry->counter($customMetric);
    $counter->incBy(1, ['value_a']);

    $samples = $registry->collectFamilySamples();
    $metricNames = array_map(fn ($family) => $family->getName(), $samples);

    expect($metricNames)->toContain('app_custom_test_metric');
});

it('observes duration using hrtime', function () {
    $registry = app(MetricsRegistryInterface::class);

    $startTime = hrtime(true);
    usleep(1000); // 1ms
    $registry->observeDuration(BuiltInMetric::HTTP_REQUEST_DURATION, $startTime, ['test', 'GET', '200']);

    $samples = $registry->collectFamilySamples();
    $metricNames = array_map(fn ($family) => $family->getName(), $samples);

    expect($metricNames)->toContain('app_http_request_duration_seconds');
});

it('uses default namespace from config', function () {
    config()->set('prometheus.default_namespace', 'myapp');

    // Need fresh registry with new config
    $this->app->forgetInstance(MetricsRegistryInterface::class);
    $registry = app(MetricsRegistryInterface::class);

    $registry->counter(BuiltInMetric::HTTP_REQUESTS)->incBy(1, ['route', 'GET', '200']);

    $samples = $registry->collectFamilySamples();
    $metricNames = array_map(fn ($family) => $family->getName(), $samples);

    expect($metricNames)->toContain('myapp_http_requests_total');
});
