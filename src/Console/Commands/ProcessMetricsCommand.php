<?php

namespace Ninja\Metronome\Console\Commands;

use Illuminate\Console\Command;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Processors\WindowProcessor;
use Ninja\Metronome\Repository\Builder\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Tasks\ProcessMetricsTask;
use Ninja\Metronome\ValueObjects\TimeWindow;
use Throwable;

final class ProcessMetricsCommand extends Command
{
    protected $signature = 'metronome:metrics:process
        {window : Window to process (realtime, hourly, daily, weekly, monthly)}
        {--process-pending : Process pending windows before current window}
        {--force : Force processing of pending windows even if marked as processed}';

    protected $description = 'Process metrics from realtime storage to persistent storage';

    public function __construct(protected readonly WindowProcessor $processor, protected readonly MetricAggregationRepository $repository)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $aggregation = Aggregation::tryFrom($this->argument('window'));

        try {
            $this->process(TimeWindow::forAggregation($aggregation));
            $this->stats($aggregation);
        } catch (Throwable $e) {
            $this->handleError($e, $aggregation);
        }
    }

    /**
     * @throws Throwable
     */
    private function process(TimeWindow $window): void
    {
        if ($this->option('process-pending')) {
            $this->processPending($window);
        }

        ProcessMetricsTask::with($window)();
    }

    private function processPending(TimeWindow $window): void
    {
        $pendingWindows = $this->processor->pending($window->aggregation);

        if ($pendingWindows->isEmpty()) {
            $this->info('No pending windows found.');

            return;
        }

        $this->info(sprintf(
            'Found %d pending %s windows to process',
            $pendingWindows->count(),
            $window->aggregation->value
        ));

        $table = [];
        foreach ($pendingWindows as $pendingWindow) {
            $this->info(sprintf('Processing pending window: %s', $pendingWindow));

            try {
                ProcessMetricsTask::with($pendingWindow, $this->getOutput())();
                $table[] = [
                    $pendingWindow->aggregation->value,
                    $pendingWindow->from->toDateTimeString(),
                    $pendingWindow->to->toDateTimeString(),
                    'Success',
                ];
            } catch (Throwable $e) {
                $table[] = [
                    $pendingWindow->aggregation->value,
                    $pendingWindow->from->toDateTimeString(),
                    $pendingWindow->to->toDateTimeString(),
                    'Failed: '.$e->getMessage(),
                ];

                if (! $this->option('force')) {
                    throw $e;
                }
            }
        }

        $this->table(
            ['Window', 'From', 'To', 'Status'],
            $table
        );
    }

    private function stats(Aggregation $window): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Last Processing', $this->processor->state()->last($window)?->diffForHumans() ?? 'Never'],
                ['Error Count', $this->processor->state()->errors($window)],
                ['Window Type', $window->value],
                ['Retention Period', config(sprintf('metronome.aggregation.retention.%s', $window->value), '7 days')],
                ['Pending Windows', $this->processor->pending($window)->count()],
            ]
        );
    }

    private function handleError(Throwable $e, Aggregation $window): void
    {
        $this->error(sprintf(
            'Processing failed for %s window. Error: %s',
            $window->value,
            $e->getMessage()
        ));

        $this->table(
            ['Metric', 'Value'],
            [
                ['Window', $window->value],
                ['Error Count', $this->processor->state()->errors($window)],
                ['Last Success', $this->processor->state()->last($window)?->diffForHumans() ?? 'Never'],
                ['Error', $e->getMessage()],
                ['Trace', $e->getTraceAsString()],
            ]
        );

        throw $e;
    }
}
