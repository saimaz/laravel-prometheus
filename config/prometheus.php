<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Prometheus Metrics
    |--------------------------------------------------------------------------
    |
    | When disabled, an in-memory adapter is used so metric calls become
    | no-ops with negligible overhead.
    |
    */
    'enabled' => env('PROMETHEUS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default Namespace
    |--------------------------------------------------------------------------
    |
    | Prefix for all metric names (e.g. "myapp_http_requests_total").
    | Defaults to a sanitized version of your APP_NAME.
    |
    */
    'default_namespace' => env('PROMETHEUS_NAMESPACE', strtolower(str_replace([' ', '-'], '_', env('APP_NAME', 'app')))),

    /*
    |--------------------------------------------------------------------------
    | Metrics Endpoint
    |--------------------------------------------------------------------------
    */
    'endpoint' => [
        'path' => env('PROMETHEUS_ENDPOINT', 'metrics'),
        'middleware' => [],
        'allowed_ips' => array_filter(explode(',', env('PROMETHEUS_ALLOWED_IPS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Supported: "redis", "apc", "in_memory"
    |
    */
    'storage' => [
        'driver' => env('PROMETHEUS_STORAGE', 'redis'),
        'redis' => [
            'connection' => env('PROMETHEUS_REDIS_CONNECTION', 'default'),
            'prefix' => env('PROMETHEUS_PREFIX', 'PROMETHEUS_'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Metrics
    |--------------------------------------------------------------------------
    |
    | Automatic request tracking via global middleware.
    | Set http.enabled to false to disable.
    |
    */
    'http' => [
        'enabled' => true,
        'duration_buckets' => [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        'ignored_routes' => ['metrics', 'horizon.*'],
        'label_provider' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collectors
    |--------------------------------------------------------------------------
    |
    | Classes implementing CollectorInterface, invoked on each /metrics scrape.
    | HorizonCollector is auto-registered when laravel/horizon is installed.
    |
    */
    'collectors' => [],

];
