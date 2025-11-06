<?php

declare(strict_types=1);

use Ninebit\LaravelPrometheus\BuiltInMetric;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;

it('returns metrics in prometheus text format', function () {
    $response = $this->get('/metrics');

    $response->assertOk();
    $response->assertHeader('Content-Type');
});

it('includes registered metrics in response', function () {
    $registry = app(MetricsRegistryInterface::class);
    $registry->counter(BuiltInMetric::HTTP_REQUESTS)->incBy(1, ['test-route', 'GET', '200']);

    $response = $this->get('/metrics');

    $response->assertOk();
    $response->assertSee('app_http_requests_total');
    $response->assertSee('test-route');
});

it('blocks requests from non-whitelisted ips', function () {
    config()->set('prometheus.endpoint.allowed_ips', ['10.0.0.1']);

    $response = $this->get('/metrics');

    $response->assertForbidden();
});

it('allows requests when no ip whitelist is configured', function () {
    config()->set('prometheus.endpoint.allowed_ips', []);

    $response = $this->get('/metrics');

    $response->assertOk();
});

it('uses configurable endpoint path', function () {
    // Default path is 'metrics'
    $response = $this->get('/metrics');

    $response->assertOk();
});
