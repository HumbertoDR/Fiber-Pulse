<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\TelemetryLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

/**
 * GeminiAnalyzerService
 *
 * Integra Google Gemini API (gemini-2.5-flash, Pay-as-you-go) para
 * análisis predictivo de telemetría en tiempo real.
 *
 * Flujo:
 * 1. Toma el historial reciente de telemetría
 * 2. Construye un prompt con las métricas
 * 3. Consulta a Gemini API con esquema JSON estructurado
 * 4. Devuelve: status, predicted_failure, recommended_action
 */
class GeminiAnalyzerService
{
    /** Clave Redis para el último análisis */
    private const RK_AI_LATEST = 'ai:latest';

    /**
     * Analizar el historial de telemetría con Gemini.
     *
     * @param Collection|null $logs Logs de telemetría recientes (últimos 20)
     * @return array {status, predicted_failure, recommended_action, source}
     */
    public function analyze(?Collection $logs = null): array
    {
        $logs = $logs ?? TelemetryLog::orderBy('recorded_at', 'desc')->take(20)->get();

        if ($logs->isEmpty()) {
            return $this->fallbackAnalysis('Sin datos de telemetría disponibles todavía.');
        }

        $apiKey = config('gemini.api_key');

        // Si no hay API key, usar análisis basado en umbrales (modo offline)
        if (empty($apiKey)) {
            return $this->thresholdAnalysis($logs);
        }

        try {
            $prompt = $this->buildPrompt($logs);
            $response = $this->callGemini($prompt);

            if (!$response->successful()) {
                Log::warning('Gemini API error: ' . $response->status() . ' - ' . $response->body());
                return $this->thresholdAnalysis($logs, 'Gemini API error: HTTP ' . $response->status());
            }

            $parsed = $this->parseResponse($response->json());

            if ($parsed === null) {
                Log::warning('Gemini API: respuesta no parseable');
                return $this->thresholdAnalysis($logs, 'Respuesta de Gemini no parseable');
            }

            $parsed['source'] = 'gemini';
            $this->storeLatest($parsed);
            return $parsed;

        } catch (\Exception $e) {
            Log::error('Gemini API excepción: ' . $e->getMessage());
            return $this->thresholdAnalysis($logs, 'Excepción: ' . $e->getMessage());
        }
    }

    /**
     * Guardar el análisis en BD y Redis.
     */
    public function persist(array $analysis, ?int $telemetryLogId = null): AiAnalysis
    {
        $record = AiAnalysis::create([
            'telemetry_log_id'  => $telemetryLogId,
            'status'            => $analysis['status'] ?? 'OPTIMAL',
            'predicted_failure' => $analysis['predicted_failure'] ?? 'Sin diagnóstico disponible.',
            'recommended_action'=> $analysis['recommended_action'] ?? 'Continuar monitoreo normal.',
            'raw_response'      => $analysis['raw_response'] ?? null,
            'analyzed_at'        => now(),
        ]);

        $analysis['id'] = $record->id;
        $this->storeLatest($analysis);

        return $record;
    }

    /**
     * Obtener el último análisis desde Redis.
     */
    public function getLatest(): ?array
    {
        $data = Redis::get(self::RK_AI_LATEST);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Guardar el último análisis en Redis.
     */
    public function storeLatest(array $analysis): void
    {
        $analysis['timestamp'] = $analysis['timestamp'] ?? now()->toISOString();
        Redis::set(self::RK_AI_LATEST, json_encode($analysis));
    }

    // ─── Construcción del prompt ───

    /**
     * Construir el prompt en texto natural para Gemini con los datos de telemetría.
     */
    private function buildPrompt(Collection $logs): string
    {
        $logs = $logs->reverse()->values();

        $lines = [];
        $lines[] = 'DATOS DE TELEMETRÍA DEL NODO (últimos ' . $logs->count() . ' registros, cada 5 segundos):';
        $lines[] = '';
        $lines[] = sprintf(
            '%-20s %6s %6s %8s %10s %8s %10s %10s',
            'Timestamp', 'CPU%', 'RAM%', 'Temp(°C)', 'Net(Mbps)', 'Lat(ms)', 'Opt(dBm)', 'Estado'
        );
        $lines[] = str_repeat('-', 85);

        foreach ($logs as $log) {
            $lines[] = sprintf(
                '%-20s %6.1f %6.1f %8.1f %10.1f %8d %10.1f %10s',
                $log->recorded_at,
                $log->cpu_usage,
                $log->ram_usage,
                $log->temperature,
                $log->network_throughput,
                $log->latency_ms,
                $log->optical_signal_dbm,
                $log->status
            );
        }

        $lines[] = '';
        $lines[] = 'INSTRUCCIONES:';
        $lines[] = '1. Analiza las tendencias de cada métrica (¿sube? ¿baja? ¿estable?).';
        $lines[] = '2. Identifica patrones de degradación o riesgo de falla.';
        $lines[] = '3. Si hay valores anómalos (CPU >80%, Temp >75°C, señal óptica < -25dBm, latencia >100ms), priorízalos.';
        $lines[] = '4. Devuelve un JSON con: status (OPTIMAL/WARNING/CRITICAL), predicted_failure (diagnóstico breve), recommended_action (pasos de mitigación).';
        $lines[] = '';
        $lines[] = 'Responde en español técnico, de forma concisa.';

        return implode("\n", $lines);
    }

    // ─── Llamada a la API ───

    /**
     * Llamar a Gemini API con el prompt y esquema JSON estructurado.
     */
    private function callGemini(string $prompt)
    {
        $url = sprintf(
            '%s/models/%s:generateContent?key=%s',
            rtrim(config('gemini.base_url'), '/'),
            config('gemini.model'),
            config('gemini.api_key')
        );

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'systemInstruction' => [
                'parts' => [['text' => config('gemini.system_prompt')]],
            ],
            'generationConfig' => [
                'temperature'      => 0.4,
                'maxOutputTokens'  => 1024,
                'responseMimeType' => 'application/json',
                'responseSchema'   => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'status' => [
                            'type' => 'STRING',
                            'enum' => ['OPTIMAL', 'WARNING', 'CRITICAL'],
                        ],
                        'predicted_failure' => [
                            'type' => 'STRING',
                        ],
                        'recommended_action' => [
                            'type' => 'STRING',
                        ],
                    ],
                    'required' => ['status', 'predicted_failure', 'recommended_action'],
                ],
            ],
        ];

        return Http::timeout(config('gemini.timeout'))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $body);
    }

    // ─── Parseo de respuesta ───

    /**
     * Extraer el JSON estructurado de la respuesta de Gemini.
     */
    private function parseResponse(?array $responseJson): ?array
    {
        if (!$responseJson || !isset($responseJson['candidates'][0]['content']['parts'][0]['text'])) {
            return null;
        }

        $text = $responseJson['candidates'][0]['content']['parts'][0]['text'];
        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $status = strtoupper($data['status'] ?? 'OPTIMAL');
        if (!in_array($status, ['OPTIMAL', 'WARNING', 'CRITICAL'])) {
            $status = 'OPTIMAL';
        }

        return [
            'status'             => $status,
            'predicted_failure'  => $data['predicted_failure'] ?? 'No se pudo determinar.',
            'recommended_action' => $data['recommended_action'] ?? 'Continuar monitoreo.',
            'raw_response'       => $responseJson,
        ];
    }

    // ─── Análisis fallback (sin Gemini API) ───

    /**
     * Análisis basado en umbrales cuando Gemini no está disponible.
     */
    private function thresholdAnalysis(Collection $logs, ?string $reason = null): array
    {
        $latest = $logs->first();

        $issues = [];
        $actions = [];

        if ($latest->cpu_usage >= 85) {
            $issues[] = "CPU en {$latest->cpu_usage}%: uso crítico detectado";
            $actions[] = "Revisar procesos con alto consumo de CPU";
        } elseif ($latest->cpu_usage >= 70) {
            $issues[] = "CPU en {$latest->cpu_usage}%: uso elevado";
            $actions[] = "Monitorear procesos activos";
        }

        if ($latest->ram_usage >= 85) {
            $issues[] = "RAM en {$latest->ram_usage}%: posible fuga de memoria";
            $actions[] = "Reiniciar servicios con alto consumo de RAM";
        } elseif ($latest->ram_usage >= 75) {
            $issues[] = "RAM en {$latest->ram_usage}%: uso elevado";
            $actions[] = "Verificar consumo de memoria por proceso";
        }

        if ($latest->temperature >= 80) {
            $issues[] = "Temperatura en {$latest->temperature}°C: riesgo de sobrecalentamiento";
            $actions[] = "Verificar ventilación y disipadores del equipo";
        } elseif ($latest->temperature >= 70) {
            $issues[] = "Temperatura en {$latest->temperature}°C: elevada";
            $actions[] = "Revisar sistema de enfriamiento";
        }

        if ($latest->optical_signal_dbm <= -25) {
            $issues[] = "Señal óptica en {$latest->optical_signal_dbm}dBm: degradación del enlace";
            $actions[] = "Inspeccionar conectores y empalmes de fibra óptica";
        }

        if ($latest->latency_ms >= 200) {
            $issues[] = "Latencia en {$latest->latency_ms}ms: congestión o pérdida de paquetes";
            $actions[] = "Ejecutar traceroute y revisar enlaces intermedios";
        } elseif ($latest->latency_ms >= 100) {
            $issues[] = "Latencia en {$latest->latency_ms}ms: elevada";
            $actions[] = "Monitorear calidad del enlace";
        }

        $status = 'OPTIMAL';
        if (!empty($issues)) {
            $status = count($issues) >= 2 ? 'CRITICAL' : 'WARNING';
        }

        $predicted = empty($issues)
            ? 'Todas las métricas se encuentran dentro de parámetros normales. El nodo opera en condiciones óptimas.'
            : implode('. ', $issues) . '.';

        $recommended = empty($actions)
            ? 'Continuar monitoreo rutinario. No se requieren acciones correctivas.'
            : implode('. ', $actions) . '.';

        if ($reason) {
            $predicted .= " (Análisis local: {$reason})";
        }

        return [
            'status'             => $status,
            'predicted_failure'  => $predicted,
            'recommended_action' => $recommended,
            'source'              => 'threshold',
            'raw_response'        => null,
        ];
    }

    /**
     * Análisis vacío cuando no hay datos.
     */
    private function fallbackAnalysis(string $message): array
    {
        return [
            'status'             => 'OPTIMAL',
            'predicted_failure'  => $message,
            'recommended_action' => 'Esperar a que el collector genere datos de telemetría.',
            'source'              => 'fallback',
            'raw_response'        => null,
        ];
    }
}