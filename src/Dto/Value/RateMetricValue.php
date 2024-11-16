<?php

namespace Ninja\Metronome\Dto\Value;

use InvalidArgumentException;

final class RateMetricValue extends AbstractMetricValue
{
    public function __construct(float $value, int $interval, int $count = 1)
    {
        parent::__construct($value, [
            'interval' => $interval,
            'count' => $count,
        ]);
    }

    public static function from(string|array $data): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new self(
            value: $data['value'],
            interval: $data['metadata']['interval'],
            count: $data['metadata']['count']
        );
    }

    protected function validate(): void
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Rate value must be non-negative');
        }
        if ($this->metadata['interval'] <= 0) {
            throw new InvalidArgumentException('Rate interval must be positive');
        }
    }

    public static function empty(): self
    {
        return new self(0.0, 60); // Default 1-minute interval
    }
}
