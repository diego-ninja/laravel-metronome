<?php

namespace Tests\Unit\Mocks\Definition;

use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Definition\AbstractMetricDefinition;

class GaugeMockDefinition extends AbstractMetricDefinition
{
    public static function create(): AbstractMetricDefinition
    {
        return new self(
            name: 'test_gauge',
            type: MetricType::Gauge,
            description: 'Test Gauge Metric',
        );
    }
}
