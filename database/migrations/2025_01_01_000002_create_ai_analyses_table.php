<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de análisis de IA: almacena los diagnósticos emitidos por Gemini.
 * Cada análisis se genera a partir del historial reciente de telemetría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telemetry_log_id')
                ->nullable()
                ->constrained('telemetry_logs')
                ->nullOnDelete();
            $table->string('status', 20)->comment('OPTIMAL | WARNING | CRITICAL');
            $table->text('predicted_failure')->comment('Diagnóstico predictivo');
            $table->text('recommended_action')->comment('Acción recomendada al técnico');
            $table->json('raw_response')->nullable()->comment('Respuesta cruda de Gemini API');
            $table->timestamp('analyzed_at')->useCurrent();
            $table->timestamps();

            $table->index('status');
            $table->index('analyzed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
