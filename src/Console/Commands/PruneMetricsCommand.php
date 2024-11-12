<?php

namespace Ninja\Metronome\Console\Commands;

use Illuminate\Console\Command;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Enums\Storage;
use Ninja\Metronome\Tasks\PruneMetricsTask;

final class PruneMetricsCommand extends Command
{
    protected $signature = 'metronome:metrics:prune
        {window : Window to process (realtime, hourly, daily, weekly, monthly, yearly, all)}
        {--storage= : Prune data from a specific storage (realtime, persistent, all)}';

    protected $description = 'Prune metrics for a specific aggregation window';

    public function handle(): void
    {
        $window = $this->argument('window');
        $storage = $this->option('storage') ? Storage::tryFrom($this->option('storage')) : Storage::default();

        if ($window === 'all') {
            $this->pruneAll($storage);
            return;
        }

        $this->prune(Aggregation::tryFrom($window), $storage);
    }

    private function prune(Aggregation $window, Storage $storage): void
    {
        $this->info(sprintf('Pruning %s expired metrics from %s storage', $window->value, $storage->value));

        PruneMetricsTask::with($window, $storage)();
    }

    private function pruneAll(Storage $storage): void
    {
        $this->info('Pruning all expired metrics from %s storage', $storage->value);

        foreach (Aggregation::cases() as $window) {
            PruneMetricsTask::with($window, $storage, $this->getOutput())();
        }
    }
}