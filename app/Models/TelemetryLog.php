<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo TelemetryLog: registro de métricas del servidor/nodo.
 *
 * Campos:
 *  - cpu_usage: Uso de CPU (%)
 *  - ram_usage: Uso de RAM (%)
 *  - temperature: Temperatura del CPU (°C)
 *  - network_throughput: Throughput de red (Mbps)
 *  - latency_ms: Latencia de red (ms)
 *  - optical_signal_dbm: Potencia de señal óptica (dBm)
 *  - status: normal | warning | critical
 *  - recorded_at: Momento de captura de la métrica
 */
class TelemetryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'cpu_usage',
        'ram_usage',
        'temperature',
        'network_throughput',
        'latency_ms',
        'optical_signal_dbm',
        'status',
        'recorded_at',
    ];

    protected $casts = [
        'cpu_usage'          => 'float',
        'ram_usage'          => 'float',
        'temperature'        => 'float',
        'network_throughput' => 'float',
        'latency_ms'         => 'integer',
        'optical_signal_dbm' => 'float',
        'recorded_at'        => 'datetime',
    ];

    protected $attributes = [
        'status' => 'normal',
    ];

    /**
     * Análisis de IA asociado a esta captura de telemetría.
     */
    public function aiAnalysis(): HasOne
    {
        return $this->hasOne(AiAnalysis::class);
    }

    /**
     * Scope: registros recientes (últimos N minutos).
     */
    public function scopeRecent($query, int $minutes = 5)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Determinar el estado basándose en umbrales de las métricas.
     *
     * Métricas donde un valor ALTO es malo: cpu, ram, temperatura, latencia.
     * Señal óptica: un valor MUY NEGATIVO es malo (se revisa aparte).
     */
    public static function determineStatus(array $metrics): string
    {
        $criticalThresholds = [
            'cpu_usage'   => 90,
            'ram_usage'   => 90,
            'temperature' => 85,
            'latency_ms'  => 200,
        ];

        $warningThresholds = [
            'cpu_usage'   => 70,
            'ram_usage'   => 75,
            'temperature' => 70,
            'latency_ms'  => 100,
        ];

        // Umbrales de señal óptica (dBm): más negativo = peor señal
        $opticalWarning  = -25.0;
        $opticalCritical = -35.0;

        foreach ($criticalThresholds as $key => $value) {
            if (isset($metrics[$key]) && $metrics[$key] >= $value) {
                return 'critical';
            }
        }

        if (isset($metrics['optical_signal_dbm']) && $metrics['optical_signal_dbm'] <= $opticalCritical) {
            return 'critical';
        }

        foreach ($warningThresholds as $key => $value) {
            if (isset($metrics[$key]) && $metrics[$key] >= $value) {
                return 'warning';
            }
        }

        if (isset($metrics['optical_signal_dbm']) && $metrics['optical_signal_dbm'] <= $opticalWarning) {
            return 'warning';
        }

        return 'normal';
    }
}