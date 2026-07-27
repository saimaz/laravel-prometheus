<?php

declare(strict_types=1);

use Ninebit\LaravelPrometheus\Support\MetricFieldMatcher;

it('matches a counter or gauge field by any label value', function (string $field, bool $expected) {
    expect((new MetricFieldMatcher(['generated::*']))->matches($field))->toBe($expected);
})->with([
    'route is generated' => ['["generated::Lxb1UUxX1M6bG8yL","GET","200"]', true],
    'generated in last position' => ['["GET","200","generated::abc"]', true],
    'named route' => ['["app.dashboard","GET","200"]', false],
    'uri pattern' => ['["app/invoices/{invoice}","GET","200"]', false],
    'substring only, not a prefix' => ['["not-generated::abc","GET","200"]', false],
    'empty label set' => ['[]', false],
]);

it('matches a histogram field through its nested labelValues', function () {
    $matcher = new MetricFieldMatcher(['generated::*']);

    expect($matcher->matches('{"b":"sum","labelValues":["generated::abc","GET","200"]}'))->toBeTrue()
        ->and($matcher->matches('{"b":0.25,"labelValues":["generated::abc","GET","200"]}'))->toBeTrue()
        ->and($matcher->matches('{"b":"sum","labelValues":["app.dashboard","GET","200"]}'))->toBeFalse();
});

it('never matches the metadata field', function () {
    // __meta holds the metric definition. Deleting it orphans the whole metric.
    expect((new MetricFieldMatcher(['*']))->matches('__meta'))->toBeFalse();
});

it('ignores fields that are not decodable series', function (string $field) {
    expect((new MetricFieldMatcher(['*']))->matches($field))->toBeFalse();
})->with([
    'not json' => ['just-a-string'],
    'json scalar' => ['"plain"'],
    'json number' => ['42'],
    'empty' => [''],
]);

it('matches when any one of several patterns hits', function () {
    $matcher = new MetricFieldMatcher(['closure', 'generated::*']);

    expect($matcher->matches('["generated::abc","GET","200"]'))->toBeTrue()
        ->and($matcher->matches('["closure","GET","200"]'))->toBeTrue()
        ->and($matcher->matches('["app.dashboard","GET","200"]'))->toBeFalse();
});

it('exposes decoded label values for both layouts', function () {
    $matcher = new MetricFieldMatcher([]);

    expect($matcher->labelValues('["a","GET","200"]'))->toBe(['a', 'GET', '200'])
        ->and($matcher->labelValues('{"b":"sum","labelValues":["a","GET"]}'))->toBe(['a', 'GET'])
        ->and($matcher->labelValues('__meta'))->toBeNull()
        ->and($matcher->labelValues('nonsense'))->toBeNull();
});
