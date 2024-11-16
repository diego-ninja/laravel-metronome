<?php

namespace Ninja\Metronome\Dto\Value;

use InvalidArgumentException;

final class SummaryMetricValue extends AbstractMetricValue
{
    public function __construct(
        float $value,
        array $quantiles,
        int $count = 1,
        float $sum = 0
    ) {
        parent::__construct($value, [
            'quantiles' => $quantiles,
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
            quantiles: $data['metadata']['quantiles'],
            count: $data['metadata']['count'],
            sum: $data['metadata']['sum']
        );
    }
    protected function validate(): void
    {
        if (empty($this->metadata['quantiles'])) {
            throw new InvalidArgumentException('Summary must have quantiles defined');
        }
    }

    public static function empty(): self
    {
        return new self(
            value: 0.0,
            quantiles: [0.5],
            count: 1,
            sum: 0.0
        );
    }
}
