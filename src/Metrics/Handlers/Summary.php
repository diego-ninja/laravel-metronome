<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;
use Ninja\Metronome\Enums\Quantile;
use Ninja\Metronome\Exceptions\InvalidMetricException;

final class Summary extends AbstractMetricHandler
{
    private array $quantiles;

    /**
     * @throws InvalidMetricException
     */
    public function __construct(?array $quantiles = null)
    {
        $quantiles = $quantiles ?? Quantile::scale();

        if (empty($quantiles)) {
            throw new InvalidMetricException('Summary must have quantiles defined');
        }

        foreach ($quantiles as $q) {
            if ($q < 0 || $q > 1) {
                throw new InvalidMetricException('Quantiles must be between 0 and 1');
            }
        }

        $this->quantiles = $quantiles;
    }

    public function compute(array $values): MetricValue
    {
        $this->validateOrFail($values);

        if (empty($values)) {
            $emptyQuantiles = array_combine(
                array_map(fn($q) => (string)$q, $this->quantiles),
                array_fill(0, count($this->quantiles), 0.0)
            );

            return new SummaryMetricValue(
                value: 0.0,
                quantiles: $emptyQuantiles,
                count: 0,
                sum: 0.0
            );
        }

        $nums = array_column($values, 'value');
        sort($nums);

        $count = count($nums);
        $sum = array_sum($nums);
        $mean = $sum / $count;

        $computedQuantiles = [];
        foreach ($this->quantiles as $q) {
            $pos = $q * ($count - 1);
            $index = floor($pos);
            $fraction = $pos - $index;

            if ($index + 1 < $count) {
                $quantileValue = $nums[$index] * (1 - $fraction) + $nums[$index + 1] * $fraction;
            } else {
                $quantileValue = $nums[$index];
            }

            $computedQuantiles[(string)$q] = $quantileValue;
        }

        return new SummaryMetricValue(
            value: $mean,
            quantiles: $computedQuantiles,
            count: $count,
            sum: $sum
        );
    }
}
