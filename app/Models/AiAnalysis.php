<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo AiAnalysis: diagnóstico emitido por Gemini API.
 *
 * Campos:
 *  - status: OPTIMAL | WARNING | CRITICAL
 *  - predicted_failure: Diagnóstico breve del riesgo
 *  - recommended_action: Pasos de mitigación para el técnico
 *  - raw_response: Respuesta cruda de la API (JSON)
 *  - analyzed_at: Momento del análisis
 */
class AiAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'telemetry_log_id',
        'status',
        'predicted_failure',
        'recommended_action',
        'raw_response',
        'analyzed_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'analyzed_at'   => 'datetime',
    ];

    /**
     * Captura de telemetría que originó este análisis.
     */
    public function telemetryLog(): BelongsTo
    {
        return $this->belongsTo(TelemetryLog::class);
    }

    /**
     * Color asociado al estado para la UI.
     */
    public function statusColor(): string
    {
        return match (strtolower($this->status)) {
            'optimal'  => 'green',
            'warning'  => 'yellow',
            'critical' => 'red',
            default    => 'slate',
        };
    }
}
