<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Exceptions\InvalidMetricException;

class Rate extends AbstractMetricHandler
{
    public const DEFAULT_RATE_INTERVAL = 3600;

    private int $interval;

    /**
     * @throws InvalidMetricException
     */
    public function __construct(int $interval = self::DEFAULT_RATE_INTERVAL)
    {
        if ($interval <= 0) {
            throw new InvalidMetricException('Rate interval must be positive');
        }

        $this->interval = $interval;
    }

    public function compute(array $values): MetricValue
    {
        $this->validateOrFail($values);

        if (empty($values)) {
            return new RateMetricValue(0.0, $this->interval, 0);
        }

        $count = count($values);
        $timestamps = array_column($values, 'timestamp');
        $duration = max($timestamps) - min($timestamps);

        if ($duration <= 0) {
            return new RateMetricValue($count, $this->interval, $count);
        }

        $rate = (($count - 1) * $this->interval) / $duration;

        return new RateMetricValue($rate, $this->interval, $count);
    }

    public function validate(array $values): bool
    {
        try {
            foreach ($values as $value) {
                if (!isset($value['value'], $value['timestamp'])) {
                    return false;
                }
                if (!is_numeric($value['value']) || !is_numeric($value['timestamp'])) {
                    return false;
                }
                if ($value['value'] < 0) {
                    return false;
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    protected function validateOrFail(array $values): void
    {
        foreach ($values as $value) {
            if (!isset($value['value'], $value['timestamp'])) {
                throw new InvalidMetricException('Invalid metric values');
            }
            if (!is_numeric($value['value']) || !is_numeric($value['timestamp'])) {
                throw new InvalidMetricException('Invalid metric values');
            }
            if ($value['value'] < 0) {
                throw new InvalidMetricException('Rate value must be non-negative');
            }
        }
    }
}