<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Contracts;

/**
 * Implement this interface on a backed enum to define metrics.
 *
 * Example:
 *   enum Metric: string implements MetricDefinition
 *   {
 *       case HTTP_REQUESTS = 'http_requests_total';
 *
 *       public function helpText(): string { ... }
 *       public function labelNames(): array { ... }
 *       public function buckets(): ?array { ... }
 *   }
 */
interface MetricDefinition
{
    public function helpText(): string;

    public function labelNames(): array;

    public function buckets(): ?array;
}
