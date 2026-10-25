<?php

namespace App\Http\Controllers;

use App\Services\GeminiAnalyzerService;
use App\Services\TelemetryCollectorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DashboardController
 *
 * Renderiza la vista principal del panel de control FiberPulse AI.
 * Inyecta datos iniciales (última telemetría, análisis IA, QR, historial)
 * que el frontend actualiza luego vía SSE.
 */
class DashboardController extends Controller
{
    public function __construct(
        private TelemetryCollectorService $collector,
        private GeminiAnalyzerService $analyzer,
    ) {}

    public function index(Request $request): View
    {
        $latest = $this->collector->getLatest();
        $analysis = $this->analyzer->getLatest();
        $anomaly = $this->collector->getActiveAnomaly();
        $history = $this->collector->getHistory(30);

        $localUrl = $this->getLocalUrl($request);
        $geminiEnabled = !empty(config('gemini.api_key'));
        $geminiModel = config('gemini.model', 'gemini-2.5-flash');

        return view('dashboard', [
            'latest'        => $latest,
            'analysis'      => $analysis,
            'anomaly'       => $anomaly,
            'history'       => $history,
            'localUrl'      => $localUrl,
            'geminiEnabled' => $geminiEnabled,
            'geminiModel'   => $geminiModel,
        ]);
    }

    /**
     * Construir la URL local accesible desde la red Wi-Fi del salón.
     * Prioridad: APP_LOCAL_IP → APP_URL → host de la petición.
     */
    private function getLocalUrl(Request $request): string
    {
        // 1) IP explícita configurada en el .env
        $customIp = env('APP_LOCAL_IP');
        if ($customIp) {
            if (str_contains($customIp, ':')) {
                return 'http://' . $customIp;
            }
            $port = $this->extractPort($request);
            return 'http://' . $customIp . ':' . $port;
        }

        // 2) APP_URL si está configurada (y no apunta a localhost)
        $appUrl = config('app.url');
        if ($appUrl && !in_array(parse_url($appUrl, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) {
            return rtrim($appUrl, '/');
        }

        // 3) Fallback: host real de la petición entrante
        return $request->getSchemeAndHttpHost();
    }

    private function extractPort(Request $request): int
    {
        $host = $request->getHttpHost();
        $parts = explode(':', $host);
        return isset($parts[1]) ? (int) $parts[1] : 8000;
    }
}