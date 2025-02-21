<?php

namespace Ninja\Metronome\Processors;

use InvalidArgumentException;
use Ninja\Metronome\Exceptions\MetricHandlerNotFoundException;
use Ninja\Metronome\Metrics\Handlers\HandlerFactory;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Processors\Contracts\Processable;
use Ninja\Metronome\Processors\Contracts\Processor;
use Ninja\Metronome\Processors\Items\Metric;
use Ninja\Metronome\Repository\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Dto\Metric as MetricDto;
use Throwable;

final readonly class MetricProcessor implements Processor
{
    public function __construct(
        private MetricStorage $storage,
        private MetricAggregationRepository $repository
    ) {
    }

    /**
     * @throws MetricHandlerNotFoundException
     * @throws Throwable
     */
    public function process(Processable $item): void
    {
        if (!$item instanceof Metric) {
            throw new InvalidArgumentException('Invalid processable type');
        }

        $value = $this->storage->value($item->key());

        if (empty($value)) {
            return;
        }

        $metric = new MetricDto(
            name: $item->key()->name,
            type: $item->key()->type,
            value: HandlerFactory::compute($item->key()->type, $value),
            timestamp: $item->window()->from,
            dimensions: $item->key()->dimensions,
            aggregation: $item->window()->aggregation
        );

        $this->repository->store($metric);
    }
}
