<?php

namespace Tests\Unit\Mocks\Definition;

use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Definition\AbstractMetricDefinition;

class AverageMockDefinition extends AbstractMetricDefinition
{
    public static function create(): AbstractMetricDefinition
    {
        return new self(
            name: 'test_average',
            type: MetricType::Average,
            description: 'Test Average Metric',
        );
    }
}