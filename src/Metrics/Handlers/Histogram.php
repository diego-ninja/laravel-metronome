<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Enums\Bucket;
use Ninja\Metronome\Exceptions\InvalidMetricException;

final class Histogram extends AbstractMetricHandler
{
    private array $buckets;

    /**
     * @throws InvalidMetricException
     */
    public function __construct(?array $buckets = null)
    {
        $buckets = $buckets ?? Bucket::Default->scale();

        if (empty($buckets)) {
            throw new InvalidMetricException('Histogram must have buckets defined');
        }

        $this->buckets = $buckets;
    }
    public function compute(array $values): MetricValue
    {
        $this->validateOrFail($values);

        $count = count($values);
        $sum = array_sum(array_column($values, 'value'));
        $mean = $count > 0 ? $sum / $count : 0;

        $buckets = array_combine(
            $this->buckets,
            array_fill(0, count($this->buckets), 0)
        );

        foreach ($values as $value) {
            foreach ($this->buckets as $le) {
                if ($value['value'] <= $le) {
                    $buckets[$le]++;
                }
            }
        }
        
        return new HistogramMetricValue(
            value: $mean,
            buckets: array_map(
                fn($le, $count) => ['le' => $le, 'count' => $count],
                array_keys($buckets),
                array_values($buckets)
            ),
            count: count($values),
            sum: array_sum(array_column($values, 'value'))
        );
    }
}
