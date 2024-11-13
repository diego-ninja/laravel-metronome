<?php

namespace Ninja\Metronome\Metrics\Storage;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Ninja\Metronome\Contracts\MetricValue;
use Ninja\Metronome\Dto\Key;
use Ninja\Metronome\Dto\Value\AverageMetricValue;
use Ninja\Metronome\Dto\Value\CounterMetricValue;
use Ninja\Metronome\Dto\Value\GaugeMetricValue;
use Ninja\Metronome\Dto\Value\HistogramMetricValue;
use Ninja\Metronome\Dto\Value\PercentageMetricValue;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Dto\Value\SummaryMetricValue;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\MetricType;
use Ninja\Metronome\Exceptions\InvalidMetricException;
use Ninja\Metronome\Exceptions\MetricHandlerNotFoundException;
use Ninja\Metronome\Metrics\Handlers\HandlerFactory;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\ValueObjects\TimeWindow;
use Swoole\Table;
use Throwable;

final readonly class MemoryMetricStorage implements MetricStorage
{
    private Table $storage;

    private Table $index;

    public function __construct(
        private ?string $prefix = null,
        int $max = 10000
    ) {
        $this->storage = new Table($max);
        $this->storage->column('value', Table::TYPE_STRING, 1024);
        $this->storage->column('type', Table::TYPE_STRING, 32);
        $this->storage->column('timestamp', Table::TYPE_INT);
        $this->storage->column('expire_at', Table::TYPE_INT);
        $this->storage->create();

        $this->index = new Table($max);
        $this->index->column('keys', Table::TYPE_STRING, 4096);
        $this->index->column('expire_at', Table::TYPE_INT);
        $this->index->create();
    }

    public function store(Key $key, MetricValue $value): void
    {
        try {
            $metricKey = $this->prefix($key);
            $timestamp = now();
            $expireAt = $timestamp->timestamp + ($key->window->seconds() * 2);

            if ($key->type === MetricType::Counter) {
                $this->updateCounter($metricKey, $value, $timestamp, $expireAt);
            } else {
                $this->storeMetric($key, $value, $timestamp, $expireAt);
            }

            $this->updateIndex($key, $expireAt);

        } catch (Throwable $e) {
            Log::error('Failed to store metric in memory', [
                'key' => (string) $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function updateCounter(string $metricKey, MetricValue $value, Carbon $timestamp, int $expireAt): void
    {
        $current = $this->storage->get($metricKey);
        if ($current !== null) {
            $currentValue = json_decode($current['value'], true);
            $newValue = $currentValue['value'] + $value->value();
        } else {
            $newValue = $value->value();
        }

        $this->storage->set($metricKey, [
            'value' => json_encode([
                'value' => $newValue,
                'timestamp' => $timestamp->timestamp,
            ]),
            'type' => MetricType::Counter->value,
            'timestamp' => $timestamp->timestamp,
            'expire_at' => $expireAt,
        ]);
    }

    private function storeMetric(Key $key, MetricValue $value, Carbon $timestamp, int $expireAt): void
    {
        $this->storage->set($this->prefix($key), [
            'value' => json_encode([
                'value' => $value->value(),
                'timestamp' => $timestamp->timestamp,
                'metadata' => $value->metadata(),
            ]),
            'type' => $key->type->value,
            'timestamp' => $timestamp->timestamp,
            'expire_at' => $expireAt,
        ]);

    }

    private function updateIndex(Key $key, int $expireAt): void
    {
        $metricKey = $this->prefix($key);
        $indexKey = $this->getIndexKey($key->window, $key->type);
        $current = $this->index->get($indexKey);
        $keys = [];

        if ($current !== null) {
            $keys = json_decode($current['keys'], true);
        }

        if (! in_array($metricKey, $keys)) {
            $keys[] = $metricKey;
        }

        $this->index->set($indexKey, [
            'keys' => json_encode($keys),
            'expire_at' => $expireAt,
        ]);

    }

    /**
     * @throws MetricHandlerNotFoundException
     * @throws InvalidMetricException
     */
    public function value(Key $key): MetricValue
    {
        $data = $this->storage->get($this->prefix($key));

        if (! $data || $data['expire_at'] < time()) {
            return $this->emptyValue($key->type);
        }

        $storedValue = json_decode($data['value'], true);
        $values = [[
            'value' => $storedValue['value'],
            'timestamp' => $data['timestamp'],
            'metadata' => $storedValue['metadata'] ?? [],
        ]];

        return HandlerFactory::compute($key->type, $values);
    }

    public function keys(string $pattern): array
    {
        $normalizedPattern = str_replace('*', '.*', $pattern);
        $keys = [];

        foreach ($this->storage as $key => $data) {
            if ($data['expire_at'] < time()) {
                $this->storage->del($key);

                continue;
            }

            if (preg_match("/$normalizedPattern/", $key)) {
                $keys[] = $this->strip($key);
            }
        }

        return $keys;
    }

    public function delete(TimeWindow|array $keys): void
    {
        if ($keys instanceof TimeWindow) {
            $keys = $this->keys($keys->key($this->prefix));
        }

        foreach ($keys as $key) {
            $this->storage->del($this->prefix($key));
        }
    }

    public function expired(TimeWindow $window): bool
    {
        $indexKey = $this->getIndexKey($window);
        $data = $this->index->get($indexKey);

        if (! $data || $data['expire_at'] < time()) {
            return true;
        }

        return empty(json_decode($data['keys'], true));
    }

    public function prune(Aggregation $window, Carbon $before): int
    {
        $count = 0;
        $beforeTimestamp = $before->timestamp;

        foreach ($this->storage as $key => $data) {
            if ($data['timestamp'] < $beforeTimestamp || $data['expire_at'] < time()) {
                $this->storage->del($key);
                $count++;
            }
        }

        return $count;
    }

    public function count(Aggregation $window): array
    {
        $counts = [];
        foreach (MetricType::cases() as $type) {
            $pattern = sprintf(
                '%s:*:%s:%s:*:*',
                $this->prefix,
                $type->value,
                $window->value
            );
            $counts[$type->value] = count($this->keys($pattern));
        }

        return [
            'total' => array_sum($counts),
            'by_type' => $counts,
        ];
    }

    public function health(): array
    {
        return [
            'status' => 'healthy',
            'metrics_count' => iterator_count($this->storage),
            'memory' => [
                'size' => $this->storage->getSize(),
                'memory_size' => $this->storage->getMemorySize(),
            ],
            'last_cleanup' => now()->toDateTimeString(),
        ];
    }

    private function getIndexKey(TimeWindow|Aggregation $window, ?MetricType $type = null): string
    {
        if ($window instanceof TimeWindow) {
            return sprintf('index:%s:%d', $window->aggregation->value, $window->slot);
        }

        return $type ?
            sprintf('index:%s:%s', $window->value, $type->value) :
            sprintf('index:%s', $window->value);
    }

    private function emptyValue(MetricType $type): MetricValue
    {
        return match ($type) {
            MetricType::Counter => CounterMetricValue::empty(),
            MetricType::Gauge => GaugeMetricValue::empty(),
            MetricType::Histogram => new HistogramMetricValue(0.0, config('metronome.buckets', [])),
            MetricType::Summary => new SummaryMetricValue(0.0, config('metronome.quantiles', [])),
            MetricType::Average => AverageMetricValue::empty(),
            MetricType::Rate => new RateMetricValue(0.0, config('metronome.rate_interval', 3600)),
            MetricType::Percentage => PercentageMetricValue::empty(),
            MetricType::Unknown => throw new \Exception('To be implemented'),
        };
    }

    private function prefix(string|Key $key): string
    {
        $key = $key instanceof Key ? (string) $key : $key;

        if (str_starts_with($key, sprintf('%s:', $this->prefix))) {
            return $key;
        }

        return sprintf('%s:%s', $this->prefix, $key);
    }

    private function strip(string $key): string
    {
        if (str_starts_with($key, $this->prefix.':')) {
            return substr($key, strlen($this->prefix) + 1);
        }

        return $key;
    }
}
