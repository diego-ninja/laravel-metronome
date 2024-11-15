<?php

namespace Ninja\Metronome\Enums;

use Ninja\Metronome\Dto\Value\AbstractMetricValue;
use Ninja\Metronome\Dto\Value\AverageMetricValue;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;

enum MetricType: string
{
    case Counter = 'counter';
    case Gauge = 'gauge';
    case Histogram = 'histogram';
    case Summary = 'summary';
    case Average = 'average';
    case Rate = 'rate';
    case Percentage = 'percentage';
    case Unknown = 'unknown';

    public static function values(): array
    {
        return [
            self::Counter->value,
            self::Gauge->value,
            self::Histogram->value,
            self::Summary->value,
            self::Average->value,
            self::Rate->value,
            self::Percentage->value,
        ];
    }

    public static function all(): array
    {
        return [
            self::Counter,
            self::Gauge,
            self::Histogram,
            self::Summary,
            self::Average,
            self::Rate,
            self::Percentage,
        ];
    }
    public function value(array|string $value): AbstractMetricValue
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return match ($this) {
            self::Counter => CounterMetricValue::from($value),
            self::Gauge => GaugeMetricValue::from($value),
            self::Average => AverageMetricValue::from($value),
            self::Rate => RateMetricValue::from($value),
            self::Percentage => PercentageMetricValue::from($value),
            self::Histogram => HistogramMetricValue::from($value),
            self::Summary => SummaryMetricValue::from($value),
            default => throw new \InvalidArgumentException('Invalid metric type'),
        };
    }
}
