# Laravel Prometheus

[![Latest Version on Packagist](https://img.shields.io/packagist/v/saimaz/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/saimaz/laravel-prometheus)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/saimaz/laravel-prometheus/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/saimaz/laravel-prometheus/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/saimaz/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/saimaz/laravel-prometheus)
[![License](https://img.shields.io/packagist/l/saimaz/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/saimaz/laravel-prometheus)

Zero-config Prometheus metrics for Laravel. Install the package, set one env var, and your app starts exporting HTTP metrics, Horizon queue stats, and custom application metrics — ready for Grafana.

Built on [promphp/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php).

## What you get out of the box

- **HTTP metrics** — request count and duration histogram, auto-registered as global middleware
- **Horizon metrics** — supervisor status, jobs/min, queue workload, wait times (auto-detected)
- **`/metrics` endpoint** — Prometheus text format, protected by IP whitelist
- **Custom metrics** — define your own via PHP backed enums
- **Extensible collectors** — gather point-in-time metrics on each scrape

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Redis (recommended) or APCu for metric storage

## Installation

```bash
composer require saimaz/laravel-prometheus
```

Add to your `.env`:

```env
PROMETHEUS_ENABLED=true
```

That's it. Visit `/metrics` to see your metrics.

### Optional configuration

Publish the config file only if you need to customize defaults:

```bash
php artisan vendor:publish --tag=prometheus-config
```

## Configuration

All configuration is done via environment variables — no config file needed for most setups.

| Variable | Default | Description |
|----------|---------|-------------|
| `PROMETHEUS_ENABLED` | `false` | Enable/disable metrics collection |
| `PROMETHEUS_NAMESPACE` | `APP_NAME` | Metric name prefix (e.g. `myapp_http_requests_total`) |
| `PROMETHEUS_STORAGE` | `redis` | Storage driver: `redis`, `apc`, `in_memory` |
| `PROMETHEUS_REDIS_CONNECTION` | `default` | Laravel Redis connection name |
| `PROMETHEUS_PREFIX` | `PROMETHEUS_` | Redis key prefix |
| `PROMETHEUS_ALLOWED_IPS` | _(empty)_ | Comma-separated IPs allowed to scrape `/metrics` |
| `PROMETHEUS_ENDPOINT` | `metrics` | Path for the metrics endpoint |

### Storage

Redis is recommended for production (metrics persist across PHP processes). The package automatically falls back to in-memory storage when:

- `PROMETHEUS_ENABLED` is `false`
- Redis connection fails (with error logged)
- Running in `testing` environment

## Built-in metrics

### HTTP metrics (automatic)

Registered as global middleware — no setup needed.

| Metric | Type | Labels |
|--------|------|--------|
| `{ns}_http_requests_total` | Counter | `route`, `method`, `status` |
| `{ns}_http_request_duration_seconds` | Histogram | `route`, `method`, `status` |

Routes are identified by name (e.g. `api.users.index`) or URI pattern (e.g. `api/users/{user}`) to prevent label cardinality explosion.

**Ignoring routes** — by default, `metrics` and `horizon.*` are ignored. Customize in config:

```php
'http' => [
    'ignored_routes' => ['metrics', 'horizon.*', 'health', 'telescope.*'],
],
```

### Horizon metrics (automatic)

Auto-detected when `laravel/horizon` is installed. No configuration needed.

| Metric | Type | Labels |
|--------|------|--------|
| `{ns}_horizon_status` | Gauge | — |
| `{ns}_horizon_master_supervisors` | Gauge | — |
| `{ns}_horizon_jobs_per_minute` | Gauge | — |
| `{ns}_horizon_recent_jobs` | Gauge | — |
| `{ns}_horizon_failed_jobs_per_hour` | Gauge | — |
| `{ns}_horizon_current_workload` | Gauge | `queue` |
| `{ns}_horizon_current_processes` | Gauge | `queue` |
| `{ns}_horizon_queue_wait_time_seconds` | Gauge | `queue` |

## Custom metrics

### 1. Define a metric enum

```php
<?php

namespace App\Prometheus;

use Ninebit\LaravelPrometheus\Contracts\MetricDefinition;

enum Metric: string implements MetricDefinition
{
    case API_CALLS = 'external_api_calls_total';
    case API_DURATION = 'external_api_duration_seconds';
    case ACTIVE_SESSIONS = 'active_sessions_total';

    public function helpText(): string
    {
        return match ($this) {
            self::API_CALLS => 'Total external API calls',
            self::API_DURATION => 'External API call duration',
            self::ACTIVE_SESSIONS => 'Number of active user sessions',
        };
    }

    public function labelNames(): array
    {
        return match ($this) {
            self::API_CALLS => ['service', 'status'],
            self::API_DURATION => ['service', 'endpoint'],
            self::ACTIVE_SESSIONS => ['guard'],
        };
    }

    public function buckets(): ?array
    {
        return match ($this) {
            self::API_DURATION => [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
            default => null,
        };
    }
}
```

### 2. Record metrics

Inject `MetricsRegistryInterface` or use the `Prometheus` facade:

```php
use App\Prometheus\Metric;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;

class EmailService
{
    public function __construct(
        private readonly MetricsRegistryInterface $metrics,
    ) {}

    public function send(string $template): void
    {
        $start = hrtime(true);

        // ... call external mail provider API ...

        $this->metrics->observeDuration(Metric::API_DURATION, $start, ['mailgun', '/v3/messages']);
        $this->metrics->counter(Metric::API_CALLS)->incBy(1, ['mailgun', 'success']);
    }
}
```

Or with the facade:

```php
use Ninebit\LaravelPrometheus\Facades\Prometheus;

Prometheus::gauge(Metric::ACTIVE_SESSIONS)->set(42, ['web']);
```

## Custom collectors

Collectors run on each `/metrics` scrape — useful for point-in-time metrics.

```php
<?php

namespace App\Prometheus\Collectors;

use App\Prometheus\Metric;
use Ninebit\LaravelPrometheus\Contracts\CollectorInterface;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;
use Illuminate\Support\Facades\DB;

class ActiveSessionsCollector implements CollectorInterface
{
    public function collect(MetricsRegistryInterface $registry): void
    {
        // Example: count active sessions from a database table
        $count = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(15)->getTimestamp())
            ->count();

        $registry->gauge(Metric::ACTIVE_SESSIONS)->set($count, ['web']);
    }
}
```

Register in `config/prometheus.php`:

```php
'collectors' => [
    \App\Prometheus\Collectors\ActiveSessionsCollector::class,
],
```

## Custom HTTP labels

Add tenant, brand, or other labels to HTTP metrics by implementing `HttpLabelProvider`:

```php
<?php

namespace App\Prometheus;

use Illuminate\Http\Request;
use Ninebit\LaravelPrometheus\Contracts\HttpLabelProvider;
use Symfony\Component\HttpFoundation\Response;

class TenantLabelProvider implements HttpLabelProvider
{
    public function labelNames(): array
    {
        return ['tenant', 'route', 'method', 'status'];
    }

    public function labelValues(Request $request, Response $response): array
    {
        return [
            $request->header('X-Tenant-ID', 'default'),
            $request->route()?->getName() ?? $request->route()?->uri() ?? 'unnamed',
            $request->getMethod(),
            (string) $response->getStatusCode(),
        ];
    }
}
```

Set in config:

```php
'http' => [
    'label_provider' => \App\Prometheus\TenantLabelProvider::class,
],
```

## Prometheus scrape config

Add your Laravel app as a target in `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'laravel'
    scrape_interval: 15s
    static_configs:
      - targets: ['your-app:8080']
    metrics_path: /metrics
```

## Testing

```bash
composer test        # Run tests
composer analyse     # PHPStan static analysis
composer format      # Fix code style with Pint
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
