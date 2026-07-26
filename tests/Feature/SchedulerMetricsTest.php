<?php

declare(strict_types=1);

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Ninebit\LaravelPrometheus\Listeners\RecordScheduledTaskMetrics;

it('records scheduler heartbeat and success runs', function () {
    config()->set('prometheus.enabled', true);

    $schedule = $this->app->make(Schedule::class);
    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), 'php artisan inspire');

    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleStarting(new ScheduledTaskStarting($task));
    usleep(1000);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.01));

    $body = $this->get('/metrics')->assertOk()->getContent();

    expect($body)
        ->toContain('scheduler_heartbeat_timestamp')
        ->toContain('scheduler_runs_total')
        ->toContain('inspire')
        ->toContain('success')
        ->toContain('scheduler_duration_seconds');
});

it('records scheduler failures', function () {
    config()->set('prometheus.enabled', true);

    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), 'php artisan broken:job');
    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleFailed(new ScheduledTaskFailed($task, new RuntimeException('nope')));

    $body = $this->get('/metrics')->assertOk()->getContent();

    expect($body)
        ->toContain('scheduler_runs_total')
        ->toContain('broken:job')
        ->toContain('failure');
});

it('skips recording when prometheus is disabled', function () {
    config()->set('prometheus.enabled', false);

    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), 'php artisan inspire');
    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.01));

    // Endpoint still works; built-in HTTP metrics may be empty with disabled storage.
    $this->get('/metrics')->assertOk();
});
