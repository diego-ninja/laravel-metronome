<?php

namespace Ninja\Metronome\Repository\Builder\Builder;

use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Repository\Builder\Dto\MetricCriteria;
use Ninja\Metronome\ValueObjects\TimeRange;

class MetricCriteriaBuilder
{
    private MetricCriteria $criteria;

    public function __construct()
    {
        $this->criteria = new MetricCriteria;
    }

    public function withName(string $name): self
    {
        $this->criteria->addName($name);

        return $this;
    }

    public function withNames(array $names): self
    {
        foreach ($names as $name) {
            $this->criteria->addName($name);
        }

        return $this;
    }

    public function withType(MetricType $type): self
    {
        $this->criteria->addType($type);

        return $this;
    }

    public function withTypes(array $types): self
    {
        foreach ($types as $type) {
            $this->criteria->addType($type);
        }

        return $this;
    }

    public function withAggregation(Aggregation $aggregation): self
    {
        $this->criteria->addAggregation($aggregation);

        return $this;
    }

    public function withAggregations(array $aggregations): self
    {
        foreach ($aggregations as $aggregation) {
            $this->criteria->addAggregation($aggregation);
        }

        return $this;
    }

    public function withTimeRange(TimeRange $timeRange): self
    {
        $this->criteria->withTimeRange($timeRange);

        return $this;
    }
}
