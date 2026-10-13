<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeTelemetryJob;
use App\Services\GeminiAnalyzerService;
use Illuminate\Console\Command;

/**
 * TelemetryAnalyzeCommand
 *
 * Dispara un análisis de IA manualmente (síncrono, sin cola).
 * Útil para pruebas rápidas o cuando se quiere ver el resultado en consola.
 *
 * Uso:  php artisan telemetry:analyze
 *       php artisan telemetry:analyze --queued   (lo envía a la cola en vez de síncrono)
 */
class TelemetryAnalyzeCommand extends Command
{
    protected $signature = 'telemetry:analyze
                            {--queued : Enviar a la cola en lugar de ejecutar síncrono}
                            {--logs=20 : Número de registros recientes a analizar}';

    protected $description = 'Ejecutar análisis predictivo de IA (Gemini) sobre telemetría reciente';

    public function handle(GeminiAnalyzerService $analyzer): int
    {
        $queued = $this->option('queued');
        $logCount = (int) $this->option('logs');

        if ($queued) {
            AnalyzeTelemetryJob::dispatch();
            $this->info('Análisis dispatchado a la cola. Revisa los logs del worker.');
            return Command::SUCCESS;
        }

        $this->info('Consultando Gemini API...');

        $logs = \App\Models\TelemetryLog::orderBy('recorded_at', 'desc')
            ->take($logCount)
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('No hay datos de telemetría. Ejecuta primero: php artisan telemetry:collect');
            return Command::FAILURE;
        }

        $bar = $this->output->createProgressBar(1);
        $bar->start();

        $analysis = $analyzer->analyze($logs);
        $record = $analyzer->persist($analysis, $logs->first()?->id);

        $bar->finish();
        $this->newLine(2);

        $this->info('═══════════════════════════════════════════════');
        $this->info('  Resultado del Análisis de IA');
        $this->info('═══════════════════════════════════════════════');
        $this->newLine();

        $statusColor = match(strtolower($record->status)) {
            'optimal'  => 'info',
            'warning'  => 'warn',
            'critical' => 'error',
            default    => 'info',
        };

        $this->$statusColor("Estado: {$record->status}");
        $this->newLine();
        $this->line("Diagnóstico predictivo:");
        $this->line("  {$record->predicted_failure}");
        $this->newLine();
        $this->line("Acción recomendada:");
        $this->line("  {$record->recommended_action}");
        $this->newLine();
        $this->line("Fuente: " . ($analysis['source'] ?? 'unknown'));
        $this->line("Guardado en BD: ID #{$record->id}");
        $this->newLine();

        return Command::SUCCESS;
    }
}
