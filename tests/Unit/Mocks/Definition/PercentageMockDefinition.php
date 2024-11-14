<?php

namespace Tests\Unit\Mocks\Definition;

use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Definition\AbstractMetricDefinition;

class PercentageMockDefinition extends AbstractMetricDefinition
{
    public static function create(): AbstractMetricDefinition
    {
        return new self(
            name: 'test_percentage',
            type: MetricType::Percentage,
            description: 'Test Percentage Metric',
        );
    }
}
