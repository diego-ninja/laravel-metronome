<?php

namespace Ninja\Metronome\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Ninja\Metronome\Dto\Metadata;
use Ninja\Metronome\Enums\MetricType;

/**
 * Class Device
 *
 *
 * @mixin \Illuminate\Database\Query\Builder
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property int $id unsigned int
 * @property string $metric_fingerprint string
 * @property string $name string
 * @property MetricType $type string
 * @property float $computed float
 * @property array $value json
 * @property array $dimensions json
 * @property Metadata $metadata json
 * @property string $window string
 * @property Carbon $timestamp datetime
 * @property Carbon $updated_at datetime
 */
class Metric extends Model
{
    protected $table = 'metronome_metrics';

    protected $fillable = [
        'metric_fingerprint',
        'name',
        'type',
        'computed',
        'value',
        'dimensions',
        'metadata',
        'timestamp',
        'window',
        'updated_at',
    ];

    public function type(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => MetricType::tryFrom($value),
            set: fn (MetricType $value) => $value->value,
        );
    }

    public function metadata(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Metadata::from(json_decode($value, true)) : new Metadata([]),
            set: fn (Metadata $value) => $value->json()
        );
    }

    public static function withFingerprint(string $fingerprint): self
    {
        return self::query()->where('metric_fingerprint', $fingerprint)->firstOrFail();
    }
}
