<?php

namespace Ninja\Metronome\Metrics\Exporter\Metric;

use InvalidArgumentException;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Exporter\Contracts\Exportable;
use Ninja\Metronome\Repository\Dto\Metric;

/**
 * @internal
 */
final readonly class Factory
{
    public static function create(Metric $metric): Exportable
    {
        return match ($metric->type) {
            MetricType::Counter => CounterExporter::from($metric),
            MetricType::Gauge => GaugeExporter::from($metric),
            MetricType::Average => AverageExporter::from($metric),
            MetricType::Histogram => HistogramExporter::from($metric),
            MetricType::Rate => RateExporter::from($metric),
            MetricType::Summary => SummaryExporter::from($metric),
            default => throw new InvalidArgumentException(sprintf('Unsupported metric type: %s', $metric->type->value)),
        };
    }
}
