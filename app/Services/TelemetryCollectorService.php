<?php

namespace App\Services;

use App\Models\TelemetryLog;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * TelemetryCollectorService
 *
 * Captura métricas del sistema anfitrión (CPU, RAM, temperatura, red, etc.)
 * en tiempo real. Usa lectura de /proc cuando está disponible (Linux/Docker)
 * y simula valores cuando no es posible obtenerlos del contenedor.
 *
 * Soporta inyección de anomalías simuladas para demostraciones del SENA.
 */
class TelemetryCollectorService
{
    /** @var array|null Lectura anterior de /proc/stat para calcular delta de CPU */
    private ?array $prevCpuStat = null;

    /** @var array|null Lectura anterior de /proc/net/dev para delta de red */
    private ?array $prevNetStat = null;

    /** @var int Timestamp de la lectura anterior para calcular tasas */
    private ?float $prevTimestamp = null;

    /** Claves Redis */
    private const RK_LATEST   = 'telemetry:latest';
    private const RK_HISTORY  = 'telemetry:history';
    private const RK_ANOMALY  = 'telemetry:anomaly';
    private const RK_PINGS    = 'telemetry:pings';

    /** Número máximo de registros en el historial de Redis (para gráficos) */
    private const HISTORY_LIMIT = 60;

    /**
     * Capturar métricas actuales del sistema.
     *
     * @return array Métricas con claves: cpu_usage, ram_usage, temperature,
     *               network_throughput, latency_ms, optical_signal_dbm, status
     */
    public function collect(): array
    {
        $mode = config('app.telemetry_mode', env('TELEMETRY_MODE', 'auto'));

        if ($mode === 'simulated') {
            $metrics = $this->simulateMetrics();
        } else {
            // 'auto' o 'real': intentar métricas reales, simular lo que falte
            $metrics = $this->collectRealMetrics();
        }

        // Aplicar anomalía inyectada por el instructor
        $metrics = $this->applyAnomaly($metrics);

        // Determinar estado basándose en umbrales
        $metrics['status'] = TelemetryLog::determineStatus($metrics);
        $metrics['recorded_at'] = now()->toDateTimeString();

        return $metrics;
    }

    /**
     * Almacenar métricas en MySQL + Redis y guardar en historial.
     */
    public function store(array $metrics): TelemetryLog
    {
        // Guardar en MySQL
        $log = TelemetryLog::create([
            'cpu_usage'          => $metrics['cpu_usage'],
            'ram_usage'          => $metrics['ram_usage'],
            'temperature'        => $metrics['temperature'],
            'network_throughput' => $metrics['network_throughput'],
            'latency_ms'         => $metrics['latency_ms'],
            'optical_signal_dbm' => $metrics['optical_signal_dbm'],
            'status'             => $metrics['status'],
            'recorded_at'        => $metrics['recorded_at'] ?? now(),
        ]);

        $metrics['id'] = $log->id;

        // Guardar latest en Redis
        Redis::set(self::RK_LATEST, json_encode($metrics));

        // Agregar al historial (lista en Redis para gráficos en vivo)
        Redis::lPush(self::RK_HISTORY, json_encode($metrics));
        Redis::lTrim(self::RK_HISTORY, 0, self::HISTORY_LIMIT - 1);

        // Procesar pings de usuarios
        $this->processPings($metrics);

        return $log;
    }

    /**
     * Obtener las últimas métricas desde Redis.
     */
    public function getLatest(): ?array
    {
        $data = Redis::get(self::RK_LATEST);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Obtener el historial reciente de métricas (para gráficos).
     */
    public function getHistory(int $count = 30): array
    {
        $items = Redis::lRange(self::RK_HISTORY, 0, $count - 1);
        $history = [];
        foreach ($items as $item) {
            $decoded = json_decode($item, true);
            if ($decoded) {
                $history[] = $decoded;
            }
        }
        // Invertir para que el más antiguo quede primero
        return array_reverse($history);
    }

    /**
     * Inyectar una anomalía simulada.
     *
     * @param string $type  Tipo: high_temp, cpu_overload, ram_leak, link_down, network_congestion
     * @param int    $duration Segundos que durará la anomalía
     */
    public function injectAnomaly(string $type, int $duration = 60): void
    {
        $anomaly = [
            'type'      => $type,
            'started_at'=> time(),
            'duration'   => $duration,
        ];
        Redis::set(self::RK_ANOMALY, json_encode($anomaly));
        Redis::expire(self::RK_ANOMALY, $duration + 5);
        Log::info("Anomalía inyectada: {$type} por {$duration}s");
    }

    /**
     * Limpiar la anomalía inyectada.
     */
    public function clearAnomaly(): void
    {
        Redis::del(self::RK_ANOMALY);
        Log::info("Anomalía limpiada manualmente");
    }

    /**
     * Obtener la anomalía activa actual.
     */
    public function getActiveAnomaly(): ?array
    {
        $data = Redis::get(self::RK_ANOMALY);
        if (!$data) {
            return null;
        }
        $anomaly = json_decode($data, true);
        if (!$anomaly) {
            return null;
        }
        // Verificar si la anomalía ya expiró
        if (time() - $anomaly['started_at'] >= $anomaly['duration']) {
            Redis::del(self::RK_ANOMALY);
            return null;
        }
        $anomaly['remaining'] = $anomaly['duration'] - (time() - $anomaly['started_at']);
        return $anomaly;
    }

    /**
     * Registrar un ping de usuario (asistentes escanean QR y disparan ping).
     */
    public function recordPing(string $userId = null): void
    {
        $ping = [
            'user_id' => $userId ?? uniqid('user_'),
            'timestamp' => time(),
            'source_ip' => request()?->ip() ?? 'unknown',
        ];
        Redis::rPush(self::RK_PINGS, json_encode($ping));
        Redis::lTrim(self::RK_PINGS, -100, -1);
    }

    /**
     * ─── Métricas reales del sistema (Linux /proc) ───
     */
    private function collectRealMetrics(): array
    {
        $now = microtime(true);

        return [
            'cpu_usage'          => $this->readCpuUsage($now),
            'ram_usage'          => $this->readRamUsage(),
            'temperature'        => $this->readTemperature(),
            'network_throughput'  => $this->readNetworkThroughput($now),
            'latency_ms'         => $this->readLatency(),
            'optical_signal_dbm' => $this->readOpticalSignal(),
        ];
    }

    /**
     * CPU: lee /proc/stat y calcula el delta entre lecturas.
     */
    private function readCpuUsage(float $now): float
    {
        if (!is_readable('/proc/stat')) {
            return $this->simulateValue(35, 15, 0, 100);
        }

        $stat = file_get_contents('/proc/stat');
        if (!preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat, $m)) {
            return $this->simulateValue(35, 15, 0, 100);
        }

        $current = [
            'user' => (int)$m[1], 'nice' => (int)$m[2], 'system' => (int)$m[3],
            'idle' => (int)$m[4], 'iowait' => (int)$m[5], 'irq' => (int)$m[6],
            'softirq' => (int)$m[7], 'steal' => (int)$m[8],
        ];

        if ($this->prevCpuStat === null || $this->prevTimestamp === null) {
            $this->prevCpuStat = $current;
            $this->prevTimestamp = $now;
            return $this->simulateValue(35, 10, 0, 100);
        }

        $totalDelta = 0;
        $idleDelta = 0;
        foreach ($current as $key => $val) {
            $delta = $val - ($this->prevCpuStat[$key] ?? 0);
            $totalDelta += $delta;
            if ($key === 'idle') {
                $idleDelta = $delta;
            }
        }

        $this->prevCpuStat = $current;
        $this->prevTimestamp = $now;

        if ($totalDelta <= 0) {
            return 0.0;
        }

        $usage = (1.0 - $idleDelta / $totalDelta) * 100;
        return round(max(0, min(100, $usage)), 1);
    }

    /**
     * RAM: lee /proc/meminfo.
     */
    private function readRamUsage(): float
    {
        if (!is_readable('/proc/meminfo')) {
            return $this->simulateValue(50, 15, 0, 100);
        }

        $meminfo = file_get_contents('/proc/meminfo');
        $total = 0;
        $available = 0;

        if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
            $total = (int)$m[1];
        }
        if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
            $available = (int)$m[1];
        }

        if ($total <= 0) {
            return $this->simulateValue(50, 15, 0, 100);
        }

        $used = $total - $available;
        return round(($used / $total) * 100, 1);
    }

    /**
     * Temperatura: intenta leer /sys/class/thermal/, simula si no está disponible.
     */
    private function readTemperature(): float
    {
        $zones = glob('/sys/class/thermal/thermal_zone*/temp');
        if (!empty($zones) && is_readable($zones[0])) {
            $raw = (int)file_get_contents($zones[0]);
            if ($raw > 0) {
                return round($raw / 1000, 1);
            }
        }
        // Simular temperatura entre 45°C y 60°C
        return $this->simulateValue(52, 6, 35, 95);
    }

    /**
     * Network throughput: lee /proc/net/dev y calcula delta.
     */
    private function readNetworkThroughput(float $now): float
    {
        if (!is_readable('/proc/net/dev')) {
            return $this->simulateValue(150, 80, 0, 1000);
        }

        $content = file_get_contents('/proc/net/dev');
        $rxBytes = 0;
        $txBytes = 0;

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\s*(eth\d+|wlan\d+|en\w+):\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
                $rxBytes += (int)$m[2];
                $txBytes += (int)$m[3];
            }
        }

        $totalBytes = $rxBytes + $txBytes;

        if ($this->prevNetStat === null || $this->prevTimestamp === null) {
            $this->prevNetStat = ['bytes' => $totalBytes];
            // prevTimestamp ya está seteado por readCpuUsage en la misma iteración
            return $this->simulateValue(150, 80, 0, 1000);
        }

        $delta = $totalBytes - $this->prevNetStat['bytes'];
        $timeDelta = $now - $this->prevTimestamp;

        $this->prevNetStat = ['bytes' => $totalBytes];

        if ($timeDelta <= 0 || $delta < 0) {
            return 0.0;
        }

        // Convertir bytes/s a Mbps
        $mbps = ($delta * 8) / ($timeDelta * 1_000_000);
        return round(max(0, $mbps), 1);
    }

    /**
     * Latencia: hace ping al gateway, simula si falla.
     */
    private function readLatency(): int
    {
        // Intentar ping al gateway predeterminado
        $gateway = $this->detectGateway();
        if ($gateway) {
            $output = @shell_exec("ping -c 1 -W 2 {$gateway} 2>/dev/null");
            if ($output && preg_match('/time=([\d.]+)/', $output, $m)) {
                return (int)round((float)$m[1]);
            }
        }
        // Simular latencia
        return (int)$this->simulateValue(8, 5, 1, 500);
    }

    /**
     * Señal óptica: simulada (no hay hardware óptico en el contenedor).
     */
    private function readOpticalSignal(): float
    {
        return round($this->simulateValue(-18.5, 1.5, -50, -5), 1);
    }

    /**
     * Detectar el gateway predeterminado del contenedor.
     */
    private function detectGateway(): ?string
    {
        $routes = @shell_exec('ip route 2>/dev/null');
        if ($routes && preg_match('/default via ([\d.]+)/', $routes, $m)) {
            return $m[1];
        }
        // Fallback: usar el host de Redis o MySQL en la red Docker
        return env('REDIS_HOST', '127.0.0.1');
    }

    // ─── Simulación ───

    /**
     * Generar métricas completamente simuladas (modo demostración).
     */
    private function simulateMetrics(): array
    {
        return [
            'cpu_usage'          => $this->simulateValue(35, 15, 0, 100),
            'ram_usage'          => $this->simulateValue(50, 15, 0, 100),
            'temperature'        => $this->simulateValue(52, 6, 35, 95),
            'network_throughput' => $this->simulateValue(150, 80, 0, 1000),
            'latency_ms'         => (int)$this->simulateValue(8, 5, 1, 500),
            'optical_signal_dbm' => round($this->simulateValue(-18.5, 1.5, -50, -5), 1),
        ];
    }

    /**
     * Simular un valor con variación suave (random walk acotado).
     */
    private function simulateValue(float $base, float $variance, float $min, float $max): float
    {
        $value = $base + (mt_rand(-100, 100) / 100) * $variance;
        return max($min, min($max, $value));
    }

    // ─── Anomalías ───

    /**
     * Aplicar anomalía inyectada a las métricas actuales.
     */
    private function applyAnomaly(array $metrics): array
    {
        $anomaly = $this->getActiveAnomaly();
        if (!$anomaly) {
            return $metrics;
        }

        switch ($anomaly['type']) {
            case 'high_temp':
                $metrics['temperature'] = mt_rand(88, 96) / 1.0;
                break;

            case 'cpu_overload':
                $metrics['cpu_usage'] = mt_rand(93, 99) / 1.0;
                $metrics['temperature'] = max($metrics['temperature'], 80);
                break;

            case 'ram_leak':
                $metrics['ram_usage'] = mt_rand(92, 98) / 1.0;
                break;

            case 'link_down':
                $metrics['optical_signal_dbm'] = round(mt_rand(-450, -380) / 10, 1);
                $metrics['latency_ms'] = mt_rand(400, 800);
                $metrics['network_throughput'] = mt_rand(0, 20) / 10;
                break;

            case 'network_congestion':
                $metrics['network_throughput'] = mt_rand(900, 1100) / 1.0;
                $metrics['latency_ms'] = mt_rand(120, 250);
                break;
        }

        return $metrics;
    }

    /**
     * Procesar pings pendientes de usuarios (cuando hay un ping reciente,
     * inyectar un pequeño pico de latencia para visualizar la reacción).
     */
    private function processPings(array &$metrics): void
    {
        $recentPings = Redis::lRange(self::RK_PINGS, -5, -1);
        $activePing = false;
        foreach ($recentPings as $raw) {
            $ping = json_decode($raw, true);
            if ($ping && (time() - $ping['timestamp']) <= 3) {
                $activePing = true;
                break;
            }
        }
        if ($activePing) {
            $metrics['latency_ms'] = max($metrics['latency_ms'], mt_rand(15, 35));
        }
    }
}
