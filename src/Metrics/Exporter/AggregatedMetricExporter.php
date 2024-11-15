<?php

namespace Ninja\Metronome\Metrics\Exporter;

use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Exporter\Metric\Factory;
use Ninja\Metronome\Repository\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Dto\Metric;

final readonly class AggregatedMetricExporter extends AbstractMetricExporter
{
    public function __construct(
        private MetricAggregationRepository $repository
    ) {
        parent::__construct();
    }

    protected function collect(): array
    {
        $aggregation = $this->aggregation();
        if ($aggregation === null) {
            return [];
        }

        $result = [];

        foreach (MetricType::cases() as $type) {
            $metrics = $this->repository->findAggregatedByType($type, $aggregation);
            $exportedMetrics = $metrics->map(function (Metric $metric) {
                return Factory::create($metric)->export();
            })->toArray();

            $result = array_merge($result, $exportedMetrics);
        }

        return $result;
    }

    private function aggregation(): ?Aggregation
    {
        $windows = Aggregation::wide();
        foreach ($windows as $window) {
            if ($this->repository->hasMetrics($window)) {
                return $window;
            }
        }

        return null;
    }
}
