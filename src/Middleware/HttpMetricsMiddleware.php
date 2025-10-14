<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ninebit\LaravelPrometheus\BuiltInMetric;
use Ninebit\LaravelPrometheus\Contracts\HttpLabelProvider;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;
use Symfony\Component\HttpFoundation\Response;

class HttpMetricsMiddleware
{
    public function __construct(
        private readonly MetricsRegistryInterface $metrics,
        private readonly ?HttpLabelProvider $labelProvider = null,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('prometheus.enabled') || ! config('prometheus.http.enabled', true)) {
            return $next($request);
        }

        $startTime = hrtime(true);

        $response = $next($request);

        // Check ignore AFTER route resolution (route is resolved inside $next)
        if ($this->shouldIgnore($request)) {
            return $response;
        }

        $labels = $this->resolveLabels($request, $response);

        $this->metrics->counter(BuiltInMetric::HTTP_REQUESTS)->incBy(1, $labels);

        $durationSeconds = (hrtime(true) - $startTime) / 1e9;
        $buckets = config('prometheus.http.duration_buckets', [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]);
        $this->metrics->histogram(BuiltInMetric::HTTP_REQUEST_DURATION, $buckets)
            ->observe($durationSeconds, $labels);

        return $response;
    }

    private function resolveLabels(Request $request, Response $response): array
    {
        if ($this->labelProvider) {
            return $this->labelProvider->labelValues($request, $response);
        }

        return [
            $this->resolveRouteName($request),
            $request->getMethod(),
            (string) $response->getStatusCode(),
        ];
    }

    private function resolveRouteName(Request $request): string
    {
        $route = $request->route();

        if ($route?->getName()) {
            return $route->getName();
        }

        // Use URI pattern instead of actual path to prevent cardinality explosion
        // e.g. "api/users/{user}" instead of "api/users/123"
        if ($route) {
            return $route->uri();
        }

        return 'unnamed';
    }

    private function shouldIgnore(Request $request): bool
    {
        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->getPathInfo(), '/');
        $ignoredRoutes = config('prometheus.http.ignored_routes', []);

        foreach ($ignoredRoutes as $pattern) {
            if (($routeName !== '' && fnmatch($pattern, $routeName)) || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
