<?php

namespace Ninja\Metronome\Dto\Value;

use InvalidArgumentException;
use Ninja\Metronome\Contracts\MetricValue;

abstract class AbstractMetricValue implements \JsonSerializable, MetricValue
{
    public function __construct(
        protected readonly float $value,
        protected readonly array $metadata = []
    ) {
        $this->validate();
    }

    public static function from(string|array $data): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new static(
            value: $data['value'],
            metadata: $data['metadata'] ?? []
        );
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
            'metadata' => $this->metadata,
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
