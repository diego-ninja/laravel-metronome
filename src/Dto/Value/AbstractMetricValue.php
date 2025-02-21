<?php

namespace Ninja\Metronome\Dto\Value;

use InvalidArgumentException;
use Ninja\Metronome\Contracts\MetricValue;

abstract class AbstractMetricValue implements MetricValue, \JsonSerializable
{
    public function __construct(
        protected readonly float $value,
        protected readonly array $metadata = []
    ) {
        $this->validate();
    }

    public function value(): float
    {
        return $this->value;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function array(): array
    {
        return [
            'value' => $this->value,
            'metadata' => $this->metadata
        ];
    }

    public function serialize(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }

    /**
     * @throws InvalidArgumentException
     */
    abstract protected function validate(): void;
}
