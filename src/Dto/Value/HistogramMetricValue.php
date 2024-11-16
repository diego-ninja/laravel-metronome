<?php

namespace Ninja\Metronome\Dto\Value;

use InvalidArgumentException;

final class HistogramMetricValue extends AbstractMetricValue
{
    public function __construct(
        float $value,
        array $buckets,
        int $count = 1,
        float $sum = 0
    ) {
        parent::__construct($value, [
            'buckets' => $buckets,
            'count' => $count,
            'sum' => $sum ?: $value,
        ]);
    }

    public static function from(string|array $data): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new self(
            value: $data['value'],
            buckets: $data['metadata']['buckets'],
            count: $data['metadata']['count'],
            sum: $data['metadata']['sum']
        );
    }

    protected function validate(): void
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Histogram value must be non-negative');
        }
        if (empty($this->metadata['buckets'])) {
            throw new InvalidArgumentException('Histogram must have buckets defined');
        }
    }

    public static function empty(): self
    {
        return new self(0.0, []);
    }
}
