<?php

namespace Ninja\Metronome\Processors;

use InvalidArgumentException;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\Processors\Contracts\Processor;
use Ninja\Metronome\Processors\Items\Metric;
use Ninja\Metronome\Repository\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Dto\Metric as MetricDto;
use Throwable;

class MetricProcessor implements Processor
{
    public function __construct(
        private readonly MetricStorage $storage,
        private readonly MetricAggregationRepository $repository
    ) {}

    /**
     * @throws Throwable
     */
    public function process(Processable $item): void
    {
        if (! $item instanceof Metric) {
            throw new InvalidArgumentException('Invalid processable type');
        }
        $value = $this->storage->value($item->key());

        if ($value->value() === 0.0) {
            return;
        }

        $metric = new MetricDto(
            name: $item->key()->name,
            type: $item->key()->type,
            value: $value,
            timestamp: $item->window()->from,
            dimensions: $item->key()->dimensions,
            aggregation: $item->window()->aggregation
        );

        $this->repository->store($metric);
    }
}
