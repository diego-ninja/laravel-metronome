<?php

use Illuminate\Support\Facades\Route;
use Ninja\Metronome\Http\Controllers\MetronomeController;

Route::prefix('metrics')->group(function () {
    Route::get('aggregated', [MetronomeController::class, 'aggregated'])
        ->name('metrics.aggregated');

    Route::get('realtime', [MetronomeController::class, 'realtime'])
        ->name('metrics.realtime');
})->middleware(['auth.basic']);
