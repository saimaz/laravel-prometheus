<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowIpsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = array_filter(config('prometheus.endpoint.allowed_ips', []));

        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps)) {
            abort(403);
        }

        return $next($request);
    }
}
