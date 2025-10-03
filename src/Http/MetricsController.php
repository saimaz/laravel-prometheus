<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Http;

use Illuminate\Http\Response;
use Ninebit\LaravelPrometheus\Contracts\CollectorInterface;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;
use Prometheus\RenderTextFormat;

class MetricsController
{
    public function __invoke(MetricsRegistryInterface $registry): Response
    {
        $this->runCollectors($registry);

        $renderer = new RenderTextFormat;

        return response($renderer->render($registry->collectFamilySamples()))
            ->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }

    private function runCollectors(MetricsRegistryInterface $registry): void
    {
        $collectors = config('prometheus.collectors', []);

        foreach ($collectors as $collectorClass) {
            try {
                $collector = app($collectorClass);

                if ($collector instanceof CollectorInterface) {
                    $collector->collect($registry);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
