<?php

declare(strict_types=1);

use Illuminate\Console\Application;
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

it('labels the real escaped command string with the artisan command name', function () {
    config()->set('prometheus.enabled', true);

    // Application::formatCommandString() escapes the php + artisan paths via
    // ProcessUtils, which is what schedule:work actually hands the listener.
    $command = Application::formatCommandString('subscriptions:renew');

    expect($command)->toContain("'"); // guard: fixture must stay realistic

    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), $command);
    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.01));

    $body = $this->get('/metrics')->assertOk()->getContent();

    expect($body)
        ->toContain('command="subscriptions:renew"')
        ->not->toContain("command=\"php'\"");
});

it('extracts the command name from every argv quoting style', function (string $command, string $expected) {
    config()->set('prometheus.enabled', true);

    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), $command);
    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.01));

    expect($this->get('/metrics')->assertOk()->getContent())
        ->toContain('command="'.$expected.'"');
})->with([
    'unix escaped' => ["'/usr/bin/php' 'artisan' queue:work --tries=3", 'queue:work'],
    'fully quoted' => ["'/usr/bin/php8.5' 'artisan' 'assets:depreciate'", 'assets:depreciate'],
    'double quoted' => ['"C:\php\php.exe" "artisan" audit:verify-chain', 'audit:verify-chain'],
    'bare' => ['php artisan exchange-rates:fetch', 'exchange-rates:fetch'],
    'absolute bare' => ['/usr/local/bin/php artisan views:refresh', 'views:refresh'],
]);

it('falls back to a clean binary name when artisan is absent', function () {
    config()->set('prometheus.enabled', true);

    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), "'/usr/bin/backup.sh' --full");
    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.01));

    // Quote must be stripped — the old code produced "backup.sh'".
    expect($this->get('/metrics')->assertOk()->getContent())
        ->toContain('command="backup.sh"');
});

it('skips recording when prometheus is disabled', function () {
    config()->set('prometheus.enabled', false);

    $task = new Event($this->app->make('Illuminate\Console\Scheduling\CacheEventMutex'), 'php artisan inspire');
    $listener = $this->app->make(RecordScheduledTaskMetrics::class);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.01));

    // Endpoint still works; built-in HTTP metrics may be empty with disabled storage.
    $this->get('/metrics')->assertOk();
});
