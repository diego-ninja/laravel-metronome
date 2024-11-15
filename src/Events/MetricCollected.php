<?php

namespace Ninja\Metronome\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Metrics\Definition\AbstractMetricDefinition;

final readonly class MetricCollected
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public AbstractMetricDefinition $definition,
        public MetricValue $value
    ) {}
}
