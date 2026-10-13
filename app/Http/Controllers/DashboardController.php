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

        return view('dashboard', [
            'latest'        => $latest,
            'analysis'      => $analysis,
            'anomaly'       => $anomaly,
            'history'       => $history,
            'localUrl'      => $localUrl,
            'geminiEnabled' => $geminiEnabled,
        ]);
    }

    /**
     * Construir la URL local accesible desde la red Wi-Fi del salón.
     * Prioriza APP_LOCAL_IP del .env, luego usa el host de la petición.
     */
    private function getLocalUrl(Request $request): string
    {
        $customIp = env('APP_LOCAL_IP');
        if ($customIp) {
            if (str_contains($customIp, ':')) {
                return 'http://' . $customIp;
            }
            $port = $this->extractPort($request);
            return 'http://' . $customIp . ':' . $port;
        }

        return $request->getSchemeAndHttpHost();
    }

    private function extractPort(Request $request): int
    {
        $host = $request->getHttpHost();
        $parts = explode(':', $host);
        return isset($parts[1]) ? (int) $parts[1] : 8080;
    }
}
