<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de telemetría: almacena métricas del servidor/nodo en tiempo real.
 * Cada registro representa una captura de métricas (cada 5 segundos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_logs', function (Blueprint $table) {
            $table->id();
            $table->float('cpu_usage')->comment('Uso de CPU (%)');
            $table->float('ram_usage')->comment('Uso de RAM (%)');
            $table->float('temperature')->comment('Temperatura del CPU (°C)');
            $table->float('network_throughput')->comment('Throughput de red (Mbps)');
            $table->integer('latency_ms')->comment('Latencia de red (ms)');
            $table->float('optical_signal_dbm')->comment('Potencia señal óptica (dBm)');
            $table->enum('status', ['normal', 'warning', 'critical'])->default('normal');
            $table->timestamp('recorded_at')->useCurrent()->comment('Momento de captura');
            $table->timestamps();

            $table->index('recorded_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_logs');
    }
};
