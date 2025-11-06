<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;

beforeEach(function () {
    // No need to manually register middleware — the package auto-registers it globally
    Route::get('/test-route', function () {
        return response('ok');
    })->name('test.route');

    Route::get('/slow-route', function () {
        usleep(10000); // 10ms

        return response('ok');
    })->name('slow.route');
});

it('tracks http request count', function () {
    $this->get('/test-route')->assertOk();
    $this->get('/test-route')->assertOk();

    $registry = app(MetricsRegistryInterface::class);
    $samples = $registry->collectFamilySamples();

    $requestMetric = collect($samples)->first(fn ($f) => $f->getName() === 'app_http_requests_total');

    expect($requestMetric)->not->toBeNull();

    $sampleValues = collect($requestMetric->getSamples())
        ->filter(fn ($s) => in_array('test.route', $s->getLabelValues()))
        ->sum(fn ($s) => $s->getValue());

    expect($sampleValues)->toEqual(2);
});

it('tracks http request duration', function () {
    $this->get('/slow-route')->assertOk();

    $registry = app(MetricsRegistryInterface::class);
    $samples = $registry->collectFamilySamples();

    $durationMetric = collect($samples)->first(fn ($f) => $f->getName() === 'app_http_request_duration_seconds');

    expect($durationMetric)->not->toBeNull();
});

it('does not track when prometheus is disabled', function () {
    config()->set('prometheus.enabled', false);

    $this->get('/test-route')->assertOk();

    $registry = app(MetricsRegistryInterface::class);
    $samples = $registry->collectFamilySamples();

    $requestMetric = collect($samples)->first(fn ($f) => $f->getName() === 'app_http_requests_total');

    expect($requestMetric)->toBeNull();
});

it('does not track when http metrics are disabled', function () {
    config()->set('prometheus.http.enabled', false);

    $this->get('/test-route')->assertOk();

    $registry = app(MetricsRegistryInterface::class);
    $samples = $registry->collectFamilySamples();

    $requestMetric = collect($samples)->first(fn ($f) => $f->getName() === 'app_http_requests_total');

    expect($requestMetric)->toBeNull();
});

it('ignores configured routes', function () {
    config()->set('prometheus.http.ignored_routes', ['test.*']);

    $this->get('/test-route')->assertOk();

    $registry = app(MetricsRegistryInterface::class);
    $samples = $registry->collectFamilySamples();

    $requestMetric = collect($samples)->first(fn ($f) => $f->getName() === 'app_http_requests_total');

    expect($requestMetric)->toBeNull();
});
