<?php

namespace Ninja\Metronome\Metrics\Exporter\Metric;

final readonly class HistogramExporter extends AbstractMetricExporter
{
    public function export(): array
    {
        $metrics = [];
        $buckets = $this->value['buckets'];

        foreach ($buckets as $bucket => $count) {
            $metrics[] = [
                'name' => sprintf('%s_bucket', $this->name),
                'type' => $this->type->value,
                'help' => sprintf('%s (bucket)', $this->help()),
                'value' => $count,
                'labels' => array_merge($this->labels(), ['le' => $bucket['le']]),
            ];
        }

        $metrics[] = [
            'name' => sprintf('%s_sum', $this->name),
            'type' => $this->type->value,
            'help' => sprintf('%s (sum)', $this->help()),
            'value' => $this->value['sum'],
            'labels' => $this->labels(),
        ];

        $metrics[] = [
            'name' => sprintf('%s_count', $this->name),
            'type' => $this->type->value,
            'help' => sprintf('%s (count)', $this->help()),
            'value' => $this->value['count'],
            'labels' => $this->labels(),
        ];

        return $metrics;
    }
}
