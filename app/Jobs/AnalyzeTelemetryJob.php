<?php

namespace App\Jobs;

use App\Services\GeminiAnalyzerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AnalyzeTelemetryJob
 *
 * Se encola en Redis y es procesada por el contenedor `worker`.
 * Consulta a Gemini API con el historial reciente de telemetría
 * y persiste el diagnóstico en la base de datos + Redis.
 *
 * Se dispatcha cuando:
 *  - El collector detecta un estado warning/critical
 *  - Periódicamente cada ~60 segundos (análisis de rutina)
 *  - El instructor lo solicita manualmente
 */
class AnalyzeTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Timeout del job (30s para que coincida con Gemini) */
    public $timeout = 45;

    /** Reintentos */
    public $tries = 2;

    public function __construct(
        public ?int $telemetryLogId = null
    ) {
        $this->onConnection('redis');
        $this->onQueue('default');
    }

    public function handle(GeminiAnalyzerService $analyzer): void
    {
        Log::info('AnalyzeTelemetryJob: iniciando análisis IA...');

        $analysis = $analyzer->analyze();
        $record = $analyzer->persist($analysis, $this->telemetryLogId);

        Log::info("AnalyzeTelemetryJob: análisis completado - {$record->status}", [
            'predicted_failure' => $record->predicted_failure,
        ]);
    }

    /**
     * Manejar fallos del job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeTelemetryJob falló: ' . $exception->getMessage());
    }
}
