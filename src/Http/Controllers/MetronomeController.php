<?php

namespace Ninja\Metronome\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Ninja\Metronome\Metrics\Exporter\AggregatedMetricExporter;
use Ninja\Metronome\Metrics\Exporter\RealtimeMetricExporter;

class MetronomeController extends Controller
{
    public function aggregated(AggregatedMetricExporter $exporter): Response
    {
        return $this->response($exporter->export());
    }

    public function realtime(RealtimeMetricExporter $exporter): Response
    {
        return $this->response($exporter->export());
    }

    private function response(string $metrics): Response
    {
        return response($metrics, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}