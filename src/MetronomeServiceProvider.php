<?php

namespace Ninja\Metronome;

use Carbon\Laravel\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Laravel\Octane\Facades\Octane;
use Ninja\Metronome\Console\Commands\ProcessMetricsCommand;
use Ninja\Metronome\Console\Commands\PruneMetricsCommand;
use Ninja\Metronome\Contracts\ShouldReportMetric;
use Ninja\Metronome\Enums\Aggregation;
use Ninja\Metronome\Metrics\Contracts\Discoverable;
use Ninja\Metronome\Metrics\Registry;
use Ninja\Metronome\Metrics\Storage\Contracts\MetricStorage;
use Ninja\Metronome\Metrics\Storage\Contracts\StateStorage;
use Ninja\Metronome\Metrics\Storage\RedisMetricStorage;
use Ninja\Metronome\Metrics\Storage\RedisStateStorage;
use Ninja\Metronome\Processors\MetricProcessor;
use Ninja\Metronome\Processors\TypeProcessor;
use Ninja\Metronome\Processors\WindowProcessor;
use Ninja\Metronome\Repository\Builder\Contracts\MetricAggregationRepository;
use Ninja\Metronome\Repository\Builder\DatabaseMetricAggregationRepository;
use Ninja\Metronome\Tasks\ProcessMetricsTask;
use Ninja\Metronome\ValueObjects\TimeWindow;

final class MetronomeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = __DIR__.'/../config/metronome.php';
        $this->mergeConfigFrom(
            path: $config,
            key: 'metronome'
        );

        $this->app->singleton(MetricStorage::class, function () {
            return new RedisMetricStorage(
                prefix: config('metronome.prefix'),
                connection: config('metronome.storage.metrics.connection')
            );
        });

        $this->app->singleton(StateStorage::class, function () {
            return new RedisStateStorage(
                prefix: config('metronome.prefix'),
                connection: config('metronome.storage.state.connection')
            );
        });

        $this->app->singleton(StateManager::class, function () {
            return new StateManager(
                app()->make(StateStorage::class)
            );
        });

        $this->app->singleton(MetricAggregationRepository::class, function () {
            return new DatabaseMetricAggregationRepository;
        });

        $this->app->singleton(MetricProcessor::class, function () {
            return new MetricProcessor(
                app()->make(MetricStorage::class),
                app()->make(MetricAggregationRepository::class)
            );
        });

        $this->app->singleton(TypeProcessor::class, function () {
            return new TypeProcessor(
                app()->make(MetricProcessor::class),
                app()->make(MetricStorage::class)
            );
        });

        $this->app->singleton(WindowProcessor::class, function () {
            return new WindowProcessor(
                app()->make(TypeProcessor::class),
                app()->make(MetricStorage::class),
                app()->make(StateManager::class)
            );
        });

        $this->app->singleton(MetricAggregator::class, function () {
            return new MetricAggregator(
                app()->make(MetricStorage::class)
            );
        });

        $this->app->singleton(MetricManager::class, function () {
            return new MetricManager(
                app()->make(WindowProcessor::class),
                app()->make(MetricStorage::class),
                app()->make(StateManager::class)
            );
        });

        if (config('metronome.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/metronome.php');
        }
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMetricProviders();
        $this->registerMetricCollectors();
        $this->registerMetricProcessor();
        $this->registerListener();

        if (config('metronome.metrics.auto_discover')) {
            \Log::info('Auto-discovering metric definitions');
            $this->discoverMetricDefinitions(app_path());
        }
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessMetricsCommand::class,
                PruneMetricsCommand::class,
            ]);
        }
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/metronome.php' => config_path('metronome.php')], 'config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'metronome-migrations');
        }
    }

    private function registerMetricProcessor(): void
    {
        if (config('metronome.processing.driver') === 'scheduler') {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                $schedule->command('metronome:metrics:process realtime --process-pending')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        } else {
            Octane::tick('realtime', function () {
                ProcessMetricsTask::with(TimeWindow::forAggregation(Aggregation::Realtime))();
            })->seconds(Aggregation::Realtime->seconds());
        }
    }

    private function registerMetricCollectors(): void
    {
        $collectors = config('metronome.metrics.collectors', []);

        foreach ($collectors as $collector) {
            app()->make($collector)->listen();
        }
    }

    private function registerMetricProviders(): void
    {
        $providers = config('metronome.metrics.providers', []);
        foreach ($providers as $provider) {
            app()->make($provider)->register();
        }

        Registry::initialize();
    }

    private function registerListener(): void
    {
        $this->app['events']->listen('*', function (string $eventName, array $payload) {
            $event = array_pop($payload);

            if ($event instanceof ShouldReportMetric) {
                collect($event->metric());
            }
        });
    }

    private function discoverMetricDefinitions(string $rootDirectory): void
    {
        collect(File::allFiles($rootDirectory))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->filter(fn ($class) => class_exists($class) && is_subclass_of($class, Discoverable::class))
            ->each(function ($class) {
                \Log::info(sprintf('Registering metric definition: %s', $class));
                Registry::register($class::create());
            });
    }
}
