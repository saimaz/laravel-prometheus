<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implement this interface to customize the labels attached to HTTP metrics.
 *
 * Example: add a "tenant" or "brand" label.
 */
interface HttpLabelProvider
{
    /**
     * Return label names (must match the order of labelValues).
     *
     * @return string[]
     */
    public function labelNames(): array;

    /**
     * Return label values for a given request/response pair.
     *
     * @return string[]
     */
    public function labelValues(Request $request, Response $response): array;
}
