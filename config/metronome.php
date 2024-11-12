<?php

use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\Bucket;
use Ninja\Metronome\Enums\Quantile;

return [
    'metronome' => [
        /*
        |--------------------------------------------------------------------------
        | Enabled
        |--------------------------------------------------------------------------
        | This option controls whether Metronome is enabled or not. When disabled,
        | Metronome will not collect or process any metrics.
        |
        */
        'enabled' => true,

        /*
        |--------------------------------------------------------------------------
        | Metric Prefix
        |--------------------------------------------------------------------------
        | This option allows you to specify a prefix for all metrics stored by
        | Metronome. This is useful when you have multiple applications sharing
        | the same Redis instance.
        |
        */
        'prefix' => 'metronome',

        /*
        |--------------------------------------------------------------------------
        | Storage
        |--------------------------------------------------------------------------
        | This options allows you to specify the storage driver for the different
        | types of data that Metronome uses. You can choose between Redis and
        | memory.
        |
        | Supported: "redis", "memory"
        */
        'storage' => [
            'metrics' => [
                'driver' => 'redis',
                'connection' => 'metrics',
                'memory' => [
                    'max_size' => 10000,
                ]
            ],
            'state' => [
                'driver' => 'redis',
                'connection' => 'metrics',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Processing
        |--------------------------------------------------------------------------
        | This options allows you to specify the processing driver for running
        | the metric processing. You can choose between scheduler and task.
        | When using task, you need to have Octane installed.
        |
        | Supported: "scheduler", "task"
        */
        'processing' => [
            'driver' => 'scheduler',
        ],

        /*
        |--------------------------------------------------------------------------
        | Aggregation
        |--------------------------------------------------------------------------
        | This options allows you to specify the aggregation windows and metric retention period.
        |
        | Supported: "Realtime", "Hourly", "Daily", "Weekly", "Monthly", "Yearly"
        */
        'aggregation' => [
            'windows' => Aggregation::cases(), // Realtime, Hourly, Daily, Weekly, Monthly, Yearly
            'retention' => [] //Override default retention period for each aggregation window
        ],

        /*
        |--------------------------------------------------------------------------
        | Metrics
        |--------------------------------------------------------------------------
        | This options allows you to specify the metrics that Metronome will collect. You can
        | specify metric providers and collectors. Providers are used to register metric definitions
        | and collectors are used to listen for events and collect metrics.
        */
        'metrics' => [
            'providers' => [
            ],
            "collectors" => [
            ]
        ],
        'dimensions' => [],
        'buckets' => Bucket::Default->scale(),
        'quantiles' => Quantile::scale(),
        'rate_interval' => 3600,
    ]
];
