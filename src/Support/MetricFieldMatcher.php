<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Support;

/**
 * Decides whether a Redis hash field (one stored series) should be pruned.
 *
 * promphp stores one Redis hash per metric, keyed by the encoded label values:
 *
 *   counter / gauge  ["app.dashboard","GET","200"]
 *   histogram        {"b":"sum","labelValues":["app.dashboard","GET","200"]}
 *   metadata         __meta                                (never a series)
 *
 * Summaries use a different layout and are not handled here — this package
 * never registers them.
 */
class MetricFieldMatcher
{
    /**
     * @param  list<string>  $patterns  fnmatch globs tested against each label value
     */
    public function __construct(
        private readonly array $patterns,
    ) {}

    public function matches(string $field): bool
    {
        foreach ($this->labelValues($field) ?? [] as $value) {
            foreach ($this->patterns as $pattern) {
                if (fnmatch($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Label values carried by a hash field, or null when the field is not a series.
     *
     * @return list<string>|null
     */
    public function labelValues(string $field): ?array
    {
        if ($field === '__meta') {
            return null;
        }

        $decoded = json_decode($field, true);

        if (! is_array($decoded)) {
            return null;
        }

        // Histogram fields wrap the label values alongside the bucket.
        if (array_key_exists('labelValues', $decoded)) {
            $decoded = $decoded['labelValues'];
        }

        if (! is_array($decoded)) {
            return null;
        }

        $values = [];

        foreach ($decoded as $value) {
            if (is_scalar($value)) {
                $values[] = (string) $value;
            }
        }

        return $values;
    }
}
