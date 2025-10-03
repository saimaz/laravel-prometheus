<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ninebit\LaravelPrometheus\Http\AllowIpsMiddleware;
use Ninebit\LaravelPrometheus\Http\MetricsController;

Route::get(config('prometheus.endpoint.path', 'metrics'), MetricsController::class)
    ->name('metrics')
    ->middleware(array_merge(
        [AllowIpsMiddleware::class],
        config('prometheus.endpoint.middleware', []),
    ));
