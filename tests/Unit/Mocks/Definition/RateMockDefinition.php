<?php

namespace Tests\Unit\Mocks\Definition;

use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Metrics\Definition\AbstractMetricDefinition;

class RateMockDefinition extends AbstractMetricDefinition
{
    public static function create(): AbstractMetricDefinition
    {
        return new self(
            name: 'test_rate',
            type: MetricType::Rate,
            description: 'Test Rate Metric',
        );
    }
}