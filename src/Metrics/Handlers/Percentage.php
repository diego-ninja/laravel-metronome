<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Exceptions\InvalidMetricException;
use Throwable;

// En Percentage.php
class Percentage extends AbstractMetricHandler
{
    public function compute(array $values): MetricValue
    {
        if (empty($values)) {
            return new PercentageMetricValue(0.0, 0.0, 0);
        }

        foreach ($values as $value) {
            if (!isset($value['metadata']['total'])) {
                throw new InvalidMetricException('Percentage total must be provided');
            }

            if ($value['value'] < 0) {
                throw new InvalidMetricException('Percentage value must be non-negative');
            }

            if ($value['metadata']['total'] < 0) {
                throw new InvalidMetricException('Percentage total must be non-negative');
            }

            if ($value['value'] > $value['metadata']['total']) {
                throw new InvalidMetricException('Percentage value cannot be greater than total');
            }
        }

        $totalValue = array_sum(array_column($values, 'value'));
        $totalSum = array_sum(array_column(array_column($values, 'metadata'), 'total'));

        return new PercentageMetricValue($totalValue, $totalSum, count($values));
    }

    public function validate(array $values): bool
    {
        try {
            foreach ($values as $value) {
                if (!isset($value['value'], $value['metadata']['total'])) {
                    return false;
                }
                if (!is_numeric($value['value']) || !is_numeric($value['metadata']['total'])) {
                    return false;
                }
                if ($value['value'] < 0 || $value['metadata']['total'] < 0) {
                    return false;
                }
                if ($value['value'] > $value['metadata']['total']) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
