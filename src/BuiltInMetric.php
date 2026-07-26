<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus;

use Ninebit\LaravelPrometheus\Contracts\MetricDefinition;

/**
 * Built-in metrics shipped with the package.
 *
 * Applications define their own MetricDefinition enums for custom metrics.
 */
enum BuiltInMetric: string implements MetricDefinition
{
    case HTTP_REQUESTS = 'http_requests_total';
    case HTTP_REQUEST_DURATION = 'http_request_duration_seconds';
    case HORIZON_SUPERVISORS = 'horizon_master_supervisors';
    case HORIZON_STATUS = 'horizon_status';
    case HORIZON_JOBS_PER_MINUTE = 'horizon_jobs_per_minute';
    case HORIZON_RECENT_JOBS = 'horizon_recent_jobs';
    case HORIZON_FAILED_JOBS = 'horizon_failed_jobs_per_hour';
    case HORIZON_WORKLOAD = 'horizon_current_workload';
    case HORIZON_PROCESSES = 'horizon_current_processes';
    case HORIZON_QUEUE_WAIT_TIME = 'horizon_queue_wait_time_seconds';

    /** Unix timestamp of the last finished scheduled task (any). Stale = scheduler dead. */
    case SCHEDULER_HEARTBEAT = 'scheduler_heartbeat_timestamp';

    /** Scheduled task runs. Labels: command, status (success|failure). */
    case SCHEDULER_RUNS = 'scheduler_runs_total';

    /** Scheduled task duration in seconds. Label: command. */
    case SCHEDULER_DURATION = 'scheduler_duration_seconds';

    public function helpText(): string
    {
        return match ($this) {
            self::HTTP_REQUESTS => 'Total HTTP requests',
            self::HTTP_REQUEST_DURATION => 'HTTP request duration in seconds',
            self::HORIZON_SUPERVISORS => 'Number of master supervisors',
            self::HORIZON_STATUS => 'Horizon status (-1=inactive, 0=paused, 1=running)',
            self::HORIZON_JOBS_PER_MINUTE => 'Jobs processed per minute',
            self::HORIZON_RECENT_JOBS => 'Number of recent jobs',
            self::HORIZON_FAILED_JOBS => 'Failed jobs per hour',
            self::HORIZON_WORKLOAD => 'Current queue workload',
            self::HORIZON_PROCESSES => 'Current processes per queue',
            self::HORIZON_QUEUE_WAIT_TIME => 'Queue wait time in seconds',
            self::SCHEDULER_HEARTBEAT => 'Unix timestamp of last finished scheduled task',
            self::SCHEDULER_RUNS => 'Total scheduled task runs by command and status',
            self::SCHEDULER_DURATION => 'Scheduled task duration in seconds',
        };
    }

    public function labelNames(): array
    {
        return match ($this) {
            self::HTTP_REQUESTS, self::HTTP_REQUEST_DURATION => ['route', 'method', 'status'],
            self::HORIZON_WORKLOAD, self::HORIZON_PROCESSES, self::HORIZON_QUEUE_WAIT_TIME => ['queue'],
            self::SCHEDULER_RUNS => ['command', 'status'],
            self::SCHEDULER_DURATION => ['command'],
            default => [],
        };
    }

    public function buckets(): ?array
    {
        return match ($this) {
            self::HTTP_REQUEST_DURATION => null, // Configured via config('prometheus.http.duration_buckets')
            self::SCHEDULER_DURATION => [0.1, 0.5, 1, 5, 15, 30, 60, 120, 300, 600],
            default => null,
        };
    }
}
