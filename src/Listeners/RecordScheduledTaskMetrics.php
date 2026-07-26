<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Ninebit\LaravelPrometheus\BuiltInMetric;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;

/**
 * Records scheduler heartbeats, run counts, and durations for Grafana.
 *
 * Wired automatically when PROMETHEUS_ENABLED=true. Metrics live in Redis
 * (same as HTTP/Horizon) so the web app /metrics scrape sees them even though
 * schedule:work runs in a separate container.
 */
class RecordScheduledTaskMetrics
{
    /** @var array<string, float> */
    private static array $startTimes = [];

    public function __construct(
        private readonly MetricsRegistryInterface $metrics,
    ) {}

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        if (! config('prometheus.enabled')) {
            return;
        }

        self::$startTimes[$this->key($event->task)] = hrtime(true);
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $this->record($event->task, 'success');
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->record($event->task, 'failure');
    }

    private function record(Event $task, string $status): void
    {
        if (! config('prometheus.enabled')) {
            return;
        }

        $command = $this->commandLabel($task);
        $key = $this->key($task);

        $this->metrics->gauge(BuiltInMetric::SCHEDULER_HEARTBEAT)->set(time());

        $this->metrics->counter(BuiltInMetric::SCHEDULER_RUNS)
            ->incBy(1, [$command, $status]);

        $start = self::$startTimes[$key] ?? null;
        unset(self::$startTimes[$key]);

        if ($start !== null) {
            $seconds = (hrtime(true) - $start) / 1e9;
            $this->metrics->histogram(BuiltInMetric::SCHEDULER_DURATION)
                ->observe($seconds, [$command]);
        }
    }

    private function key(Event $task): string
    {
        return spl_object_hash($task);
    }

    /**
     * Bounded label: prefer Artisan command name, else a short description.
     * Never use full argv with unique args (cardinality).
     *
     * Laravel builds the command via Application::formatCommandString(), which
     * runs the php and artisan paths through ProcessUtils::escapeArgument() —
     * on Unix that single-quotes them, e.g.
     * "'/usr/bin/php' 'artisan' queue:work --tries=3". Quotes must therefore be
     * tolerated around both "artisan" and the command name, otherwise every
     * task collapses onto the basename of the PHP binary ("php'").
     */
    private function commandLabel(Event $task): string
    {
        $command = $task->command ?? null;

        if (is_string($command) && $command !== '') {
            // "'/usr/bin/php' 'artisan' foo:bar --opt" → "foo:bar"
            if (preg_match('/artisan[\'"]?\s+[\'"]?([^\s\'"]+)/', $command, $m)) {
                return $this->truncate($m[1]);
            }

            $first = strtok($command, ' ') ?: $command;

            return $this->truncate(basename(trim($first, '\'"')));
        }

        if (is_string($task->description) && $task->description !== '') {
            return $this->truncate($task->description);
        }

        return 'closure';
    }

    private function truncate(string $value): string
    {
        return mb_substr($value, 0, 80);
    }
}
