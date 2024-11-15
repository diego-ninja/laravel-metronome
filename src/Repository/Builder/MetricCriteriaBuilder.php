<?php

namespace Ninja\Metronome\Repository\Builder;

use Ninja\Metronome\Dto\Dimension;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Repository\Dto\MetricCriteria;
use Ninja\Metronome\ValueObjects\TimeRange;

class MetricCriteriaBuilder
{
    private array $criteria = [];
    public function build(): MetricCriteria
    {
        return MetricCriteria::from($this->criteria);
    }

    public function withNames(array $names): self
    {
        $this->criteria['names'] = $names;
        return $this;
    }

    public function withName(string $name): self
    {
        $this->criteria['names'][] = $name;
        return $this;
    }

    public function withTypes(array $types): self
    {
        $this->criteria['types'] = $types;
        return $this;
    }

    public function withType(string $type): self
    {
        $this->criteria['types'][] = $type;
        return $this;
    }

    public function withAggregations(array $aggregations): self
    {
        $this->criteria['aggregations'] = $aggregations;
        return $this;
    }

    public function withAggregation(Aggregation $aggregation): self
    {
        $this->criteria['aggregations'][] = $aggregation;
        return $this;
    }

    public function withTimeRange(TimeRange $timeRange): self
    {
        $this->criteria['timeRange'] = $timeRange;
        return $this;
    }

    public function withDimensions(array $dimensions): self
    {
        $this->criteria['dimensions'] = $dimensions;
        return $this;
    }

    public function withDimension(Dimension $dimension): self
    {
        $this->criteria['dimensions'][] = $dimension;
        return $this;
    }
}
