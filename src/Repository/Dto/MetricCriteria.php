<?php

namespace Ninja\Metronome\Repository\Dto;

use JsonSerializable;
use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\ValueObjects\TimeRange;

readonly class MetricCriteria implements JsonSerializable
{
    /**
     * @param  string[]|null  $names
     * @param  MetricType[]|null  $types
     */
    public function __construct(
        public ?array $names = null,
        public ?array $types = null,
        public ?array $aggregations = null,
        public ?TimeRange $timeRange = null,
        public ?array $dimensions = null
    ) {}

    public static function from(string|array $data): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new self(
            names: $data['names'] ?? null,
            types: $data['types'] ?? null,
            aggregations: $data['aggregations'] ?? null,
            timeRange: $data['timeRange'] ?? null,
            dimensions: $data['dimensions'] ?? null,
        );
    }

    public function array(): array
    {
        return [
            'names' => $this->names,
            'types' => $this->types,
            'aggregations' => $this->aggregations,
            'timeRange' => $this->timeRange->array(),
            'dimensions' => $this->dimensions,
        ];
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}
