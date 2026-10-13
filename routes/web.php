<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FiberPulse AI - Rutas del panel de telemetría
|--------------------------------------------------------------------------
*/

// Dashboard principal
Route::get('/', [DashboardController::class, 'index']);

// Telemetría: SSE stream + historial
Route::get('/telemetry/stream', [TelemetryController::class, 'stream']);
Route::get('/telemetry/history', [TelemetryController::class, 'history']);

// Interacciones de asistentes
Route::post('/telemetry/ping', [TelemetryController::class, 'ping']);
Route::post('/telemetry/alert', [TelemetryController::class, 'triggerAlert']);

// Panel del instructor: inyección de anomalías
Route::post('/telemetry/inject/{type}', [TelemetryController::class, 'injectAnomaly']);
Route::post('/telemetry/clear', [TelemetryController::class, 'clearAnomaly']);
