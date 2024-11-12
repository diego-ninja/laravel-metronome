<?php

namespace Ninja\Metronome\Tasks;

use Illuminate\Console\OutputStyle;
use Ninja\Metronome\Processors\Items\Window;
use Ninja\Metronome\Processors\WindowProcessor;
use Ninja\Metronome\ValueObjects\TimeWindow;
use Throwable;

final readonly class ProcessMetricsTask
{
    private WindowProcessor $processor;

    private function __construct(private TimeWindow $window, private ?OutputStyle $output = null)
    {
        $this->processor = app(WindowProcessor::class);
    }

    /**
     * @throws Throwable
     */
    public function __invoke(): void
    {
        try {
            $this->processor->process(new Window($this->window));
            $this->output?->info(sprintf('Processing %s window: [%s]', $this->window->aggregation->value, $this->window));

            $next = $this->window->next();
            while ($next !== null) {
                $this->output?->info(sprintf('Processing %s window: [%s]', $next->aggregation->value, $next));
                $this->processor->process(new Window($next));
                $next = $next->next();
            }
        } catch (Throwable $e) {
            $this->output?->error(sprintf(
                'Failed to process metrics for %s: %s',
                $this->window->aggregation->value,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    public static function with(TimeWindow $window, ?OutputStyle $output = null): self
    {
        if (app()->runningInConsole()) {
            return new self($window, $output);
        }

        return new self($window);
    }
}
