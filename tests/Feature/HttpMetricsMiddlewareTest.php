<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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

    // Mirrors a route-cached deploy: `route:cache` compiles the collection and
    // AbstractRouteCollection assigns "generated::{Str::random()}" to every
    // unnamed route, re-rolled on each build. Uncached test routes keep a null
    // name, so the name must be set explicitly to reproduce production.
    Route::get('/unnamed-route/{id}', function () {
        return response('ok');
    })->name('generated::'.Str::random());
});

/**
 * Resolve the label values recorded for app_http_requests_total.
 *
 * @return list<list<string>>
 */
function recordedRequestLabels(): array
{
    $family = collect(app(MetricsRegistryInterface::class)->collectFamilySamples())
        ->first(fn ($f) => $f->getName() === 'app_http_requests_total');

    return $family === null
        ? []
        : collect($family->getSamples())->map(fn ($s) => $s->getLabelValues())->all();
}

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

it('labels unnamed routes with the uri pattern, never a generated name', function () {
    // Laravel auto-assigns "generated::{random}" to unnamed routes while matching.
    // That value changes on every boot, so it must never reach a metric label.
    $this->get('/unnamed-route/123')->assertOk();

    $labels = collect(recordedRequestLabels())->flatten();

    expect($labels)->toContain('unnamed-route/{id}');

    $generated = $labels->filter(fn (string $v) => str_starts_with($v, 'generated::'));

    expect($generated)->toBeEmpty();
});

it('keeps the label stable when a redeploy re-rolls the generated name', function () {
    $this->get('/unnamed-route/1')->assertOk();

    // Simulate the next deploy: same route, freshly generated random name.
    Route::get('/unnamed-route/{id}', fn () => response('ok'))
        ->name('generated::'.Str::random());

    $this->get('/unnamed-route/2')->assertOk();

    // Both requests must land on ONE series. Before the fix each boot created
    // its own series, so Prometheus cardinality grew with every deploy.
    $matching = collect(recordedRequestLabels())
        ->filter(fn (array $values) => in_array('unnamed-route/{id}', $values, true));

    expect($matching)->toHaveCount(1)
        ->and(collect(recordedRequestLabels())->flatten()->filter(
            fn (string $v) => str_starts_with($v, 'generated::')
        ))->toBeEmpty();
});

it('still applies ignored_routes patterns to unnamed routes by path', function () {
    config()->set('prometheus.http.ignored_routes', ['unnamed-route/*']);

    $this->get('/unnamed-route/123')->assertOk();

    expect(recordedRequestLabels())->toBeEmpty();
});
