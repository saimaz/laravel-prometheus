<?php

declare(strict_types=1);

namespace Ninebit\LaravelPrometheus\Collectors;

use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Ninebit\LaravelPrometheus\BuiltInMetric;
use Ninebit\LaravelPrometheus\Contracts\CollectorInterface;
use Ninebit\LaravelPrometheus\Contracts\MetricsRegistryInterface;

class HorizonCollector implements CollectorInterface
{
    public function __construct(
        private readonly MasterSupervisorRepository $supervisors,
        private readonly JobRepository $jobs,
        private readonly MetricsRepository $horizonMetrics,
        private readonly WorkloadRepository $workloads,
    ) {}

    public function collect(MetricsRegistryInterface $registry): void
    {
        try {
            $this->collectSupervisorMetrics($registry);
            $this->collectJobMetrics($registry);
            $this->collectWorkloadMetrics($registry);
        } catch (\Throwable $e) {
            Log::warning('HorizonCollector failed', [
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function collectSupervisorMetrics(MetricsRegistryInterface $registry): void
    {
        $supervisors = $this->supervisors->all();

        $registry->gauge(BuiltInMetric::HORIZON_SUPERVISORS)->set(count($supervisors));

        $status = match (true) {
            empty($supervisors) => -1,
            collect($supervisors)->contains(fn ($s) => $s->status === 'paused') => 0,
            default => 1,
        };

        $registry->gauge(BuiltInMetric::HORIZON_STATUS)->set($status);
    }

    private function collectJobMetrics(MetricsRegistryInterface $registry): void
    {
        $registry->gauge(BuiltInMetric::HORIZON_JOBS_PER_MINUTE)
            ->set((float) $this->horizonMetrics->jobsProcessedPerMinute());

        $registry->gauge(BuiltInMetric::HORIZON_RECENT_JOBS)
            ->set($this->jobs->countRecent());

        $registry->gauge(BuiltInMetric::HORIZON_FAILED_JOBS)
            ->set($this->jobs->countRecentlyFailed());
    }

    private function collectWorkloadMetrics(MetricsRegistryInterface $registry): void
    {
        $workloads = collect($this->workloads->get())->sortBy('name')->values();

        foreach ($workloads as $workload) {
            $registry->gauge(BuiltInMetric::HORIZON_WORKLOAD)->set($workload['length'], [$workload['name']]);
            $registry->gauge(BuiltInMetric::HORIZON_PROCESSES)->set($workload['processes'], [$workload['name']]);
            $registry->gauge(BuiltInMetric::HORIZON_QUEUE_WAIT_TIME)->set($workload['wait'], [$workload['name']]);
        }
    }
}
