<?php

namespace Ninja\Metronome\Dto\Value;

use InvalidArgumentException;

final class GaugeMetricValue extends AbstractMetricValue
{
    public function __construct(float $value, ?int $timestamp = null)
    {
        parent::__construct($value, ['timestamp' => $timestamp ?? time()]);
    }

    public static function from(string|array $data): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new self(
            value: $data['value'],
            timestamp: $data['metadata']['timestamp'] ?? now()->timestamp
        );
    }

    protected function validate(): void
    {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Gauge value must be non-negative');
        }
    }

    public static function empty(): self
    {
        return new self(0.0);
    }
}
