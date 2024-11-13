<?php

use Ninja\Metronome\Metrics\Handlers\Rate;
use Ninja\Metronome\Dto\Value\RateMetricValue;
use Ninja\Metronome\Exceptions\InvalidMetricException;

beforeEach(function () {
    $this->interval = 3600; // 1 hora
    $this->handler = new Rate($this->interval);
});

it('computes rate for events in interval', function () {
    $now = time();
    $values = [
        ['value' => 1, 'timestamp' => $now - 1800],       // hace 30 min
        ['value' => 1, 'timestamp' => $now - 1200],       // hace 20 min
        ['value' => 1, 'timestamp' => $now - 600],        // hace 10 min
    ];

    $result = $this->handler->compute($values);

    // 2 intervalos en 30 minutos = 6 eventos por hora (3600s)
    expect($result)->toBeInstanceOf(RateMetricValue::class)
        ->and($result->value())->toBe(6.0)
        ->and($result->metadata())->toHaveKeys(['interval', 'count'])
        ->and($result->metadata()['interval'])->toBe(3600)
        ->and($result->metadata()['count'])->toBe(3);
});

it('handles empty values', function () {
    $result = $this->handler->compute([]);

    expect($result)->toBeInstanceOf(RateMetricValue::class)
        ->and($result->value())->toBe(0.0)
        ->and($result->metadata()['count'])->toBe(0)
        ->and($result->metadata()['interval'])->toBe($this->interval);
});

it('handles single timestamp events', function () {
    $now = time();
    $values = [
        ['value' => 1, 'timestamp' => $now],
    ];

    $result = $this->handler->compute($values);

    expect($result)->toBeInstanceOf(RateMetricValue::class)
        ->and($result->value())->toBe(1.0)
        ->and($result->metadata()['count'])->toBe(1);
});

it('validates positive interval', function () {
    expect(fn() => new Rate(0))
        ->toThrow(InvalidMetricException::class, 'Rate interval must be positive');
});

it('throws exception for negative values', function () {
    $values = [
        ['value' => -1, 'timestamp' => time()]
    ];

    expect(fn() => $this->handler->compute($values))
        ->toThrow(InvalidMetricException::class, 'Rate value must be non-negative');
});

test('rate validation scenarios', function (array $values, bool $expected) {
    expect($this->handler->validate($values))->toBe($expected);
})->with([
    'valid values' => [
        'values' => [
            ['value' => 1.0, 'timestamp' => time()],
            ['value' => 2.0, 'timestamp' => time() - 300]
        ],
        'expected' => true
    ],
    'zero value valid' => [
        'values' => [['value' => 0.0, 'timestamp' => time()]],
        'expected' => true
    ],
    'negative value invalid' => [
        'values' => [['value' => -1.0, 'timestamp' => time()]],
        'expected' => false
    ],
    'missing value key' => [
        'values' => [['timestamp' => time()]],
        'expected' => false
    ],
    'missing timestamp key' => [
        'values' => [['value' => 1.0]],
        'expected' => false
    ]
]);

it('computes correct rate for different time spans', function () {
    $now = time();
    $testCases = [
        [
            'values' => [
                ['value' => 1, 'timestamp' => $now - 3600],      // 1 hora atrás
                ['value' => 1, 'timestamp' => $now],             // ahora
            ],
            'expected_rate' => 1.0                               // 1 evento por hora
        ],
        [
            'values' => [
                ['value' => 1, 'timestamp' => $now - 1800],      // 30 min atrás
                ['value' => 1, 'timestamp' => $now],             // ahora
            ],
            'expected_rate' => 2.0                               // 2 eventos por hora
        ],
        [
            'values' => [
                ['value' => 1, 'timestamp' => $now - 900],       // 15 min atrás
                ['value' => 1, 'timestamp' => $now],             // ahora
            ],
            'expected_rate' => 4.0                               // 4 eventos por hora
        ]
    ];

    foreach ($testCases as $case) {
        $result = $this->handler->compute($case['values']);
        expect($result->value())->toEqual($case['expected_rate']);
    }
});
