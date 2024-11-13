<?php

namespace Ninja\Metronome\Metrics\Handlers;

use Exception;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Exceptions\MetricHandlerNotFoundException;

final class HandlerFactory
{
    private static ?HandlerCollection $handlers = null;

    private function __construct() {}

    /**
     * @throws MetricHandlerNotFoundException
     * @throws InvalidMetricException
     */
    public static function compute(MetricType $type, array $rawValue): MetricValue
    {
        $handler = self::handlers()->get($type);
        if (! $handler) {
            throw MetricHandlerNotFoundException::forType($type);
        }

        return $handler->compute($rawValue);
    }

    public static function handlers(): HandlerCollection
    {
        if (self::$handlers === null) {
            self::initialize();
        }

        return self::$handlers;
    }

    private static function initialize(): void
    {
        self::$handlers = new HandlerCollection;

        self::$handlers->add(
            MetricType::Counter,
            new Counter
        );

        self::$handlers->add(
            MetricType::Gauge,
            new Gauge
        );

        self::$handlers->add(
            MetricType::Histogram,
            new Histogram
        );

        self::$handlers->add(
            MetricType::Average,
            new Average
        );

        self::$handlers->add(
            MetricType::Rate,
            new Rate
        );

        self::$handlers->add(
            MetricType::Summary,
            new Summary
        );

        self::$handlers->add(
            MetricType::Percentage,
            new Percentage
        );
    }

    /**
     * This method should only be used in testing.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$handlers = null;
    }

    /**
     * Prevent cloning of the instance
     *
     * @internal
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     *
     * @internal
     *
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}
