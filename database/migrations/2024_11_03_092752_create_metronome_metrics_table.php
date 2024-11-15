<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const TABLE = 'metronome';

    private string $table;

    public function __construct()
    {
        $this->table = config('metronome.table_name', self::TABLE);
    }

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('metric_fingerprint', 64);
            $table->string('name');
            $table->string('type', 32);
            $table->float('computed');
            $table->json('value');
            $table->json('dimensions');
            $table->json('metadata');
            $table->timestamp('timestamp');
            $table->string('window', 32);
            $table->timestamps();

            $table->unique('metric_fingerprint', 'device_metrics_unique');

            $table->index(['name', 'timestamp']);
            $table->index(['window', 'timestamp']);
            $table->index(['type', 'window']);
            $table->index(['name', 'type', 'window']);
            $table->index('name');
            $table->index('timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
