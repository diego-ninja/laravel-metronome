<?php

namespace Ninja\Metronome\Repository;

use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\ValueObjects\TimeRange;

class MetricCriteria
{
    /**
     * @param string[]|null $names
     * @param MetricType[]|null $types
     * @param Aggregation[]|null $windows
     */
    public function __construct(
        public ?array $names = null,
        public ?array $types = null,
        public ?array $windows = null,
        public ?TimeRange $timeRange = null,
        public ?array $dimensions = null
    ) {
    }

    public static function forName(string $name): self
    {
        return new self(names: [$name]);
    }

    public static function forType(MetricType $type): self
    {
        return new self(types: [$type]);
    }

    public static function forWindow(Aggregation $window): self
    {
        return new self(windows: [$window]);
    }

    public static function forTimeRange(TimeRange $timeRange): self
    {
        return new self(timeRange: $timeRange);
    }

    public static function forDimensions(array $dimensions): self
    {
        return new self(dimensions: $dimensions);
    }

    public function withNames(array $names): self
    {
        return new self(
            names: $names,
            types: $this->types,
            windows: $this->windows,
            timeRange: $this->timeRange,
            dimensions: $this->dimensions
        );
    }

    public function withTypes(array $types): self
    {
        return new self(
            names: $this->names,
            types: $types,
            windows: $this->windows,
            timeRange: $this->timeRange,
            dimensions: $this->dimensions
        );
    }

    public function withWindows(array $windows): self
    {
        return new self(
            names: $this->names,
            types: $this->types,
            windows: $windows,
            timeRange: $this->timeRange,
            dimensions: $this->dimensions
        );
    }

    public function withTimeRange(TimeRange $timeRange): self
    {
        return new self(
            names: $this->names,
            types: $this->types,
            windows: $this->windows,
            timeRange: $timeRange,
            dimensions: $this->dimensions
        );
    }

    public function withDimensions(array $dimensions): self
    {
        return new self(
            names: $this->names,
            types: $this->types,
            windows: $this->windows,
            timeRange: $this->timeRange,
            dimensions: $dimensions
        );
    }
}
