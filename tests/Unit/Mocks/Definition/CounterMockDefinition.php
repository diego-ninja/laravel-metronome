<?php

namespace Tests\Unit\Mocks\Definition;

use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Definition\AbstractMetricDefinition;

class CounterMockDefinition extends AbstractMetricDefinition
{
    public static function create(): AbstractMetricDefinition
    {
        return new self(
            name: 'test_counter',
            type: MetricType::Counter,
            description: 'Test Counter Metric',
        );
    }
}
