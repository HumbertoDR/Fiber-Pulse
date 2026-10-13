<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeTelemetryJob;
use App\Services\GeminiAnalyzerService;
use App\Services\TelemetryCollectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TelemetryController
 *
 * Endpoints de telemetría en tiempo real:
 *  - GET  /telemetry/stream   → Server-Sent Events (SSE)
 *  - GET  /telemetry/history   → Historial reciente (para gráficos)
 *  - POST /telemetry/ping      → Ping de prueba (asistentes)
 *  - POST /telemetry/alert     → Alerta simulada (asistentes)
 *  - POST /telemetry/inject/{type} → Inyectar anomalía (instructor)
 *  - POST /telemetry/clear     → Limpiar anomalía (instructor)
 */
class TelemetryController extends Controller
{
    /** Tipos de anomalías válidas */
    private const ANOMALY_TYPES = [
        'high_temp'          => 'Alta Temperatura de CPU',
        'cpu_overload'       => 'Sobrecarga de CPU',
        'ram_leak'           => 'Fuga de Memoria RAM',
        'link_down'          => 'Caída de Enlace Óptico',
        'network_congestion' => 'Congestión de Red',
    ];

    public function __construct(
        private TelemetryCollectorService $collector,
        private GeminiAnalyzerService $analyzer,
    ) {}

    /**
     * ─── SSE: Stream de telemetría en tiempo real ───
     * Envía eventos telemetry, analysis y anomaly a los clientes conectados.
     */
    public function stream(): StreamedResponse
    {
        ignore_user_abort(true);
        set_time_limit(300);

        $clientId = uniqid('sse_', true);

        return response()->stream(function () use ($clientId) {
            $lastTelemetryId = 0;
            $lastAnalysisTs = '';
            $iteration = 0;

            while (!connection_aborted()) {
                $iteration++;

                // ─── Heartbeat (mantener conexión viva) ───
                echo ": heartbeat\n\n";

                // ─── Registrar cliente activo (para conteo de usuarios) ───
                Redis::zadd('sse:clients', microtime(true), $clientId);
                Redis::zremrangebyscore('sse:clients', 0, microtime(true) - 10);

                // ─── Enviar telemetría si hay datos nuevos ───
                $telemetry = $this->collector->getLatest();
                if ($telemetry && ($telemetry['id'] ?? 0) > $lastTelemetryId) {
                    echo "event: telemetry\n";
                    echo "data: " . json_encode($telemetry) . "\n\n";
                    $lastTelemetryId = $telemetry['id'] ?? 0;
                }

                // ─── Enviar análisis de IA si cambió ───
                $analysis = $this->analyzer->getLatest();
                $analysisTs = $analysis['timestamp'] ?? '';
                if ($analysis && $analysisTs !== $lastAnalysisTs) {
                    echo "event: analysis\n";
                    echo "data: " . json_encode($analysis) . "\n\n";
                    $lastAnalysisTs = $analysisTs;
                }

                // ─── Enviar estado de anomalía ───
                $anomaly = $this->collector->getActiveAnomaly();
                echo "event: anomaly\n";
                echo "data: " . json_encode($anomaly) . "\n\n";

                // ─── Enviar conteo de usuarios cada 5 iteraciones ───
                if ($iteration % 5 === 0) {
                    $userCount = Redis::zcard('sse:clients');
                    echo "event: connections\n";
                    echo "data: " . json_encode(['count' => $userCount]) . "\n\n";
                }

                // ─── Flush output ───
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();

                sleep(3);
            }

            // Cleanup al desconectar
            Redis::zrem('sse:clients', $clientId);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'      => 'no-cache, no-transform',
            'X-Accel-Buffering'  => 'no',
            'Connection'         => 'keep-alive',
        ]);
    }

    /**
     * ─── Historial reciente de telemetría (para inicializar gráficos) ───
     */
    public function history(Request $request): JsonResponse
    {
        $count = min((int) $request->query('count', 30), 60);
        $history = $this->collector->getHistory($count);

        return response()->json([
            'data' => $history,
        ]);
    }

    /**
     * ─── Ping de prueba (asistentes escanean QR y disparan ping) ───
     */
    public function ping(Request $request): JsonResponse
    {
        $this->collector->recordPing($request->ip());

        $latest = $this->collector->getLatest();

        return response()->json([
            'success'    => true,
            'message'    => "Ping enviado desde {$request->ip()}",
            'latency_ms' => $latest['latency_ms'] ?? null,
            'timestamp'  => now()->toISOString(),
        ]);
    }

    /**
     * ─── Alerta simulada (asistentes disparan alerta para ver reacción IA) ───
     */
    public function triggerAlert(Request $request): JsonResponse
    {
        // Inyectar una anomalía leve de latencia por 15s
        $this->collector->injectAnomaly('network_congestion', 15);

        // Dispatch análisis IA inmediato para que los alumnos vean la reacción
        AnalyzeTelemetryJob::dispatch();

        return response()->json([
            'success'   => true,
            'message'   => 'Alerta simulada activada. La IA analizará la situación en tiempo real.',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * ─── Inyectar anomalía (panel del instructor) ───
     */
    public function injectAnomaly(Request $request, string $type): JsonResponse
    {
        if (!isset(self::ANOMALY_TYPES[$type])) {
            return response()->json([
                'success' => false,
                'message' => "Tipo de anomalía inválido: {$type}",
                'valid_types' => array_keys(self::ANOMALY_TYPES),
            ], 400);
        }

        $duration = min((int) $request->input('duration', 60), 300);

        $this->collector->injectAnomaly($type, $duration);

        // Dispatch análisis IA para diagnosticar la anomalía
        AnalyzeTelemetryJob::dispatch();

        return response()->json([
            'success'  => true,
            'message'  => 'Anomalía "' . self::ANOMALY_TYPES[$type] . '" inyectada por ' . $duration . ' segundos',
            'anomaly'  => [
                'type'     => $type,
                'label'    => self::ANOMALY_TYPES[$type],
                'duration' => $duration,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * ─── Limpiar anomalía (panel del instructor) ───
     */
    public function clearAnomaly(): JsonResponse
    {
        $this->collector->clearAnomaly();
        AnalyzeTelemetryJob::dispatch();

        return response()->json([
            'success'   => true,
            'message'   => 'Anomalía desactivada. Sistema volviendo a normalidad.',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * ─── Lista de anomalías disponibles (para el panel del instructor) ───
     */
    public static function getAnomalyTypes(): array
    {
        return self::ANOMALY_TYPES;
    }
}