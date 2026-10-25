<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeTelemetryJob;
use App\Services\GeminiAnalyzerService;
use App\Services\TelemetryCollectorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * TelemetryCollectCommand
 *
 * Ejecuta el collector de telemetría en un bucle continuo.
 * Captura métricas cada N segundos (default: 5), las guarda en
 * MySQL + Redis y dispatcha análisis de IA cuando detecta anomalías.
 *
 * Uso:  php artisan telemetry:collect
 *       php artisan telemetry:collect --interval=3 --max=100
 *
 * Corre dentro del contenedor `collector` en docker-compose.
 */
class TelemetryCollectCommand extends Command
{
    protected $signature = 'telemetry:collect
                            {--interval=5 : Segundos entre capturas}
                            {--max=0 : Número máximo de iteraciones (0 = infinito)}';

    protected $description = 'Capturar métricas de telemetría en tiempo real (bucle continuo cada 5s)';

    /** Contador de iteraciones */
    private int $iteration = 0;

    /** Última vez que se dispatchó análisis de rutina */
    private int $lastRoutineAnalysis = 0;

    /** Última vez que se dispatchó análisis por anomalía */
    private int $lastAnomalyAnalysis = 0;

    /** Estado de la lectura anterior */
    private string $lastStatus = 'normal';

    /** Intervalo para análisis de rutina (segundos) */
    private const ROUTINE_ANALYSIS_INTERVAL = 60;

    /** Espera mínima entre análisis por anomalía si el estado no cambia (segundos) */
    private const ANOMALY_COOLDOWN = 30;

    public function handle(TelemetryCollectorService $collector, GeminiAnalyzerService $analyzer): int
    {
        $interval = (int) $this->option('interval');
        $max = (int) $this->option('max');

        $this->info('═══════════════════════════════════════════════');
        $this->info('  FiberPulse AI - Telemetry Collector');
        $this->info('  Intervalo: ' . $interval . 's | Máximo: ' . ($max > 0 ? $max . ' iteraciones' : 'infinito'));
        $this->info('═══════════════════════════════════════════════');

        // Marcar el collector como activo en Redis
        \Illuminate\Support\Facades\Redis::set('telemetry:collector:status', 'active');
        \Illuminate\Support\Facades\Redis::set('telemetry:collector:started_at', now()->toISOString());

        while (true) {
            try {
                $metrics = $collector->collect();
                $log = $collector->store($metrics);

                $this->iteration++;

                // Log conciso en consola
                $statusIcon = match($metrics['status']) {
                    'normal'   => '✓',
                    'warning'  => '⚠',
                    'critical' => '✗',
                    default    => '?',
                };

                $this->line(sprintf(
                    '[%s] #%d  CPU:%5.1f%%  RAM:%5.1f%%  Temp:%5.1f°C  Net:%6.1fMb  Lat:%3dms  Opt:%5.1fdBm  %s %s',
                    now()->format('H:i:s'),
                    $this->iteration,
                    $metrics['cpu_usage'],
                    $metrics['ram_usage'],
                    $metrics['temperature'],
                    $metrics['network_throughput'],
                    $metrics['latency_ms'],
                    $metrics['optical_signal_dbm'],
                    $statusIcon,
                    strtoupper($metrics['status'])
                ));

                $status = $metrics['status'];
                $isAnomaly = in_array($status, ['warning', 'critical']);
                $statusChanged = $status !== $this->lastStatus;
                $cooldownPassed = (time() - $this->lastAnomalyAnalysis) >= self::ANOMALY_COOLDOWN;

                if ($isAnomaly && ($statusChanged || $cooldownPassed)) {
                    // Anomalía nueva o sigue activa: llamar a Gemini de forma síncrona
                    // para que el diagnóstico esté disponible de inmediato en Redis/SSE
                    $this->warn('  → Anomalía detectada, consultando Gemini...');
                    try {
                        $analysis = $analyzer->analyze();
                        $analyzer->persist($analysis, $log->id);
                        $this->warn('  → Análisis IA completado: ' . $analysis['status']);
                    } catch (\Exception $e) {
                        Log::error('Gemini síncrono falló: ' . $e->getMessage());
                        $this->error('  → Gemini falló, usando análisis local');
                    }
                    $this->lastAnomalyAnalysis = time();
                    $this->lastRoutineAnalysis = time();
                } elseif ((time() - $this->lastRoutineAnalysis) >= self::ROUTINE_ANALYSIS_INTERVAL) {
                    // Análisis de rutina cada 60s: se envía a la cola (no urge)
                    AnalyzeTelemetryJob::dispatch($log->id);
                    $this->info('  → Análisis de rutina dispatchado a la cola');
                    $this->lastRoutineAnalysis = time();
                }

                $this->lastStatus = $status;

                // Verificar límite de iteraciones
                if ($max > 0 && $this->iteration >= $max) {
                    $this->info("Máximo de iteraciones alcanzado ({$max}).");
                    break;
                }

                // Esperar antes de la siguiente captura
                sleep($interval);

            } catch (\Exception $e) {
                Log::error('TelemetryCollector error: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                sleep($interval);
            }
        }

        \Illuminate\Support\Facades\Redis::set('telemetry:collector:status', 'stopped');
        return Command::SUCCESS;
    }
}