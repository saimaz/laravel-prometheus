<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Contracts;

/**
 * Collectors gather point-in-time metrics when the /metrics endpoint is scraped.
 *
 * Use collectors for metrics that should be refreshed on each scrape
 * (e.g., Horizon queue stats, database connection pool sizes).
 */
interface CollectorInterface
{
    public function collect(MetricsRegistryInterface $registry): void;
}
