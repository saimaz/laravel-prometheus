<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Ninebit\LaravelPrometheus\Support\MetricFieldMatcher;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

/**
 * Removes stored series whose label values match a glob.
 *
 * Redis keeps every series it has ever seen, so a label that turns out to be
 * junk (a random per-boot route name, a mis-parsed command) is re-exported on
 * every scrape forever — fixing the code stops new junk but never clears the
 * old. This prunes exactly the matching series and leaves every other counter,
 * gauge, and histogram untouched, which matters when unrelated gauges (import
 * freshness, MRR) would only be repopulated by a daily job.
 */
class PruneLabelsCommand extends Command
{
    protected $signature = 'prometheus:prune-labels
        {--match=* : Glob tested against every label value, e.g. "generated::*" (repeatable)}
        {--dry-run : Report what would be removed without writing to Redis}';

    protected $description = 'Delete stored metric series whose label values match a glob';

    public function handle(): int
    {
        /** @var list<string> $patterns */
        $patterns = array_values(array_filter(
            (array) $this->option('match'),
            fn (mixed $pattern): bool => is_string($pattern) && $pattern !== ''
        ));

        if ($patterns === []) {
            $this->components->error('Pass at least one --match pattern, e.g. --match="generated::*"');

            return self::FAILURE;
        }

        $driver = (string) config('prometheus.storage.driver', 'redis');

        if ($driver !== 'redis') {
            $this->components->error("Only the redis storage driver can be pruned; configured driver is [{$driver}].");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $matcher = new MetricFieldMatcher($patterns);
        $connection = Redis::connection((string) config('prometheus.storage.redis.connection', 'default'));
        $metricPrefix = (string) config('prometheus.storage.redis.prefix', 'PROMETHEUS_');
        $clientPrefix = (string) config('database.redis.options.prefix', '');

        $this->components->info(
            ($dryRun ? 'Dry run — matching series against: ' : 'Pruning series matching: ').implode(', ', $patterns)
        );

        $total = 0;

        foreach ([Counter::TYPE, Gauge::TYPE, Histogram::TYPE] as $type) {
            /** @var list<string> $members */
            $members = (array) $connection->smembers($metricPrefix.$type.'_METRIC_KEYS');

            foreach ($members as $member) {
                $total += $this->pruneMetric(
                    $connection,
                    $this->withoutClientPrefix((string) $member, $clientPrefix),
                    $matcher,
                    $dryRun,
                );
            }
        }

        $this->newLine();

        if ($total === 0) {
            $this->components->info('No stored series matched.');

            return self::SUCCESS;
        }

        $this->components->info(
            $dryRun
                ? "{$total} series would be removed. Re-run without --dry-run to apply."
                : "{$total} series removed."
        );

        return self::SUCCESS;
    }

    private function pruneMetric(mixed $connection, string $hashKey, MetricFieldMatcher $matcher, bool $dryRun): int
    {
        /** @var list<string> $fields */
        $fields = (array) $connection->hkeys($hashKey);

        $stale = array_values(array_filter(
            $fields,
            fn (mixed $field): bool => $matcher->matches((string) $field)
        ));

        if ($stale === []) {
            return 0;
        }

        $this->components->twoColumnDetail(
            $this->metricName($hashKey),
            ($dryRun ? 'would remove ' : 'removed ').count($stale).' series'
        );

        if (! $dryRun) {
            // Chunked so a metric with thousands of stale series cannot blow up
            // the argument list of a single HDEL.
            foreach (array_chunk($stale, 500) as $chunk) {
                $connection->hdel($hashKey, ...$chunk);
            }
        }

        return count($stale);
    }

    /**
     * The Lua scripts store already-prefixed keys as set members, so the client
     * prefix has to come off before the key is used through the same client
     * again — otherwise it would be applied twice.
     */
    private function withoutClientPrefix(string $key, string $prefix): string
    {
        if ($prefix !== '' && str_starts_with($key, $prefix)) {
            return substr($key, strlen($prefix));
        }

        return $key;
    }

    private function metricName(string $hashKey): string
    {
        $position = strrpos($hashKey, ':');

        return $position === false ? $hashKey : substr($hashKey, $position + 1);
    }
}
