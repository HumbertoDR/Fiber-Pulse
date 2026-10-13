<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="FiberPulse AI - SENA IAoT Telemetry Dashboard - Monitoreo en tiempo real con IA predictiva">
    <title>FiberPulse AI · SENA IAoT Telemetry Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-[#0a0e17] grid-bg text-slate-800 dark:text-slate-200 transition-colors duration-400">

    <!-- ═══ TOGGLE NAV ═══ -->
    <nav class="sticky top-0 z-50 glass-card border-x-0 border-t-0 rounded-none">
        <div class="container mx-auto px-4 max-w-7xl">
            <div class="flex items-center justify-between h-14">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-gradient-to-br from-[#00ffd5] to-[#a855f7]">
                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-bold gradient-text leading-none">FiberPulse AI</h1>
                        <p class="text-[10px] uppercase tracking-widest opacity-50 leading-none mt-0.5">SENA IAoT Dashboard</p>
                    </div>
                </div>

                <!-- Center: System status -->
                <div class="hidden md:flex items-center gap-5">
                    <div class="flex items-center gap-2">
                        <span id="system-led" class="led led-normal led-pulse"></span>
                        <span id="system-status-text" class="text-xs font-bold uppercase tracking-wider text-green-600 dark:text-[#00ff88]">NORMAL</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="opacity-50 text-xs">SSE:</span>
                        <span id="sse-status" class="text-xs font-medium text-slate-500">Conectando...</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span id="user-count" class="text-xs font-bold">0</span>
                        <span class="text-xs opacity-50">usuarios</span>
                    </div>
                    <span id="gemini-badge" class="status-badge bg-cyan-100 text-cyan-700 dark:bg-[#00ffd5]/15 dark:text-[#00ffd5]">Gemini 2.5 Flash</span>
                </div>

                <!-- Right: Clock + Theme toggle -->
                <div class="flex items-center gap-4">
                    <span id="dashboard-clock" class="hidden sm:block text-sm font-mono tabular-nums opacity-60"></span>
                    <button id="theme-toggle" class="toggle-switch bg-slate-300 dark:bg-[#1e293b]" aria-label="Cambiar tema">
                        <span class="toggle-knob bg-white dark:bg-[#00ffd5] flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-slate-600 dark:text-[#0a0e17]" id="theme-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="4"/>
                                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- ═══ MAIN ═══ -->
    <main class="container mx-auto px-4 py-6 max-w-7xl">

        <!-- ─── AI Diagnosis Banner ─── -->
        <section id="ai-banner" class="glass-card rounded-2xl p-5 mb-4 border border-green-200 dark:border-[#00ff88]/30">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <!-- Status badge -->
                <div class="flex md:flex-col items-center md:items-start gap-2 md:gap-1 md:min-w-[140px]">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 0v10l4 2"/></svg>
                        <span class="text-xs uppercase tracking-wider opacity-50 font-medium">Análisis IA</span>
                    </div>
                    <span id="ai-status" class="status-badge bg-green-100 text-green-700 dark:bg-[#00ff88]/15 dark:text-[#00ff88]">OPTIMAL</span>
                    <span id="ai-source" class="text-xs font-medium text-neon-cyan">Gemini 2.5 Flash</span>
                </div>

                <!-- Divider -->
                <div class="hidden md:block w-px h-16 bg-slate-200 dark:bg-slate-700/40"></div>

                <!-- Diagnosis text -->
                <div class="flex-1 space-y-2">
                    <div>
                        <p class="text-xs uppercase tracking-wider opacity-50 font-medium mb-1">Diagnóstico Predictivo</p>
                        <p id="ai-predicted" class="text-sm leading-relaxed">{{ $analysis['predicted_failure'] ?? 'Iniciando sistema de monitoreo. Esperando datos de telemetría...' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider opacity-50 font-medium mb-1">Acción Recomendada</p>
                        <p id="ai-action" class="text-sm leading-relaxed opacity-80">{{ $analysis['recommended_action'] ?? 'El collector de telemetría se activará automáticamente.' }}</p>
                    </div>
                </div>

                <!-- Timestamp -->
                <div class="md:text-right md:min-w-[100px]">
                    <p class="text-xs uppercase tracking-wider opacity-50 font-medium mb-1">Actualizado</p>
                    <p id="ai-timestamp" class="text-sm font-mono tabular-nums">{{ isset($analysis['timestamp']) ? \Illuminate\Support\Carbon::parse($analysis['timestamp'])->format('H:i:s') : '--:--:--' }}</p>
                </div>
            </div>
        </section>

        <!-- ─── Anomaly Banner (hidden by default) ─── -->
        <section id="anomaly-banner" class="hidden glass-card rounded-2xl p-4 mb-4 border border-[#ff3366]/30 animate-critical-glow">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-[#ff3366]/15 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600 dark:text-[#ff3366]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider opacity-50 font-medium">Anomalía Simulada Activa</p>
                        <p id="anomaly-label" class="text-sm font-bold text-red-600 dark:text-[#ff3366]">--</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span id="anomaly-countdown" class="text-sm font-mono tabular-nums opacity-70">--s restantes</span>
                    <button id="clear-anomaly-btn" class="px-4 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-600 dark:text-[#ff3366] text-sm font-medium transition-all">
                        Detener
                    </button>
                </div>
            </div>
        </section>

        <!-- ─── Main Grid ─── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- ═══ LEFT: Metrics + Charts ═══ -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Metric Cards Grid -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">

                    @php
                        $metricIcons = [
                            'cpu'     => '<path d="M4 4h16v16H4z"/><path d="M9 9h6v6H9z"/>',
                            'ram'     => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 12h2M11 12h2M15 12h2"/>',
                            'temp'    => '<path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4 4 0 1 0 5 0z"/>',
                            'net'     => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
                            'latency' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                            'fiber'   => '<path d="M2 12h4M18 12h4M8 12c2 0 2-4 4-4s2 4 4 4M8 12c2 0 2 4 4 4s2-4 4-4"/>',
                        ];
                        $metricDefs = [
                            ['key' => 'cpu_usage', 'id' => 'cpu', 'label' => 'Uso de CPU', 'unit' => '%', 'isPercent' => true],
                            ['key' => 'ram_usage', 'id' => 'ram', 'label' => 'Uso de RAM', 'unit' => '%', 'isPercent' => true],
                            ['key' => 'temperature', 'id' => 'temp', 'label' => 'Temperatura', 'unit' => '°C', 'isPercent' => true],
                            ['key' => 'network_throughput', 'id' => 'net', 'label' => 'Throughput', 'unit' => 'Mbps', 'isPercent' => false],
                            ['key' => 'latency_ms', 'id' => 'latency', 'label' => 'Latencia', 'unit' => 'ms', 'isPercent' => false],
                            ['key' => 'optical_signal_dbm', 'id' => 'optical', 'label' => 'Señal Óptica', 'unit' => 'dBm', 'isPercent' => false],
                        ];
                    @endphp

                    @foreach ($metricDefs as $m)
                    <div class="glass-card rounded-2xl p-4 hover:scale-[1.02] transition-transform">
                        <div class="flex items-start justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $metricIcons[$m['id']] ?? '' !!}</svg>
                                <p class="text-xs font-medium uppercase tracking-wider opacity-60">{{ $m['label'] }}</p>
                            </div>
                            <span id="metric-{{ $m['id'] }}-led" class="led led-normal"></span>
                        </div>
                        <div class="flex items-baseline gap-1 mt-2">
                            <span id="metric-{{ $m['id'] }}-value" class="text-3xl font-bold text-green-600 dark:text-[#00ff88] neon-green">
                                {{ isset($latest[$m['key']]) ? (is_float($latest[$m['key']]) ? number_format($latest[$m['key']], 1) : $latest[$m['key']]) : '--' }}
                            </span>
                            <span class="text-sm opacity-50">{{ $m['unit'] }}</span>
                        </div>
                        @if ($m['isPercent'])
                        <div class="w-full bg-slate-200 dark:bg-slate-700/30 rounded-full h-1.5 mt-3 overflow-hidden">
                            <div id="metric-{{ $m['id'] }}-bar" class="h-full rounded-full bg-green-500 dark:bg-[#00ff88] transition-all duration-500" style="width: {{ $latest[$m['key']] ?? 0 }}%"></div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                <!-- Charts Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @php
                        $chartDefs = [
                            ['id' => 'cpu', 'label' => 'CPU (%)', 'max' => 100],
                            ['id' => 'ram', 'label' => 'RAM (%)', 'max' => 100],
                            ['id' => 'temp', 'label' => 'Temperatura (°C)', 'max' => 100],
                            ['id' => 'net', 'label' => 'Throughput (Mbps)', 'max' => null],
                        ];
                    @endphp
                    @foreach ($chartDefs as $c)
                    <div class="glass-card rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-70">{{ $c['label'] }}</h3>
                            <span class="led led-normal"></span>
                        </div>
                        <div class="chart-container">
                            <canvas id="chart-{{ $c['id'] }}"></canvas>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- ═══ RIGHT: QR + Panels ═══ -->
            <div class="space-y-4">

                <!-- QR Code Panel -->
                <div class="glass-card rounded-2xl p-5 text-center">
                    <h3 class="text-xs font-semibold uppercase tracking-wider opacity-70 mb-3">Conexión Rápida (QR)</h3>
                    <div class="inline-block p-3 bg-white rounded-2xl shadow-lg">
                        <canvas id="qr-canvas"></canvas>
                    </div>
                    <div class="mt-3">
                        <p id="local-url-text" class="text-sm font-mono font-medium opacity-70 break-all">{{ $localUrl }}</p>
                    </div>
                    <button id="copy-url-btn" class="mt-3 px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 hover:bg-slate-200 dark:hover:bg-slate-700 text-sm font-medium transition-all w-full">
                        <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copiar URL
                    </button>
                    <p class="text-xs opacity-40 mt-3">Escanea para conectarte al dashboard</p>
                </div>

                <!-- Connection Info -->
                <div class="glass-card rounded-2xl p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider opacity-70 mb-3">Estado de Conexión</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="opacity-60">Usuarios conectados</span>
                            <span class="flex items-center gap-1.5">
                                <span class="led led-normal led-pulse"></span>
                                <span id="user-count-side" class="font-bold tabular-nums">{{ $userCount ?? 0 }}</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="opacity-60">SSE Stream</span>
                            <span id="sse-status-side" class="text-xs font-medium text-slate-500">Conectando...</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="opacity-60">Motor IA</span>
                            @if($geminiEnabled)
                                <span class="status-badge bg-cyan-100 text-cyan-700 dark:bg-[#00ffd5]/15 dark:text-[#00ffd5] text-[10px]">Gemini Activo</span>
                            @else
                                <span class="status-badge bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 text-[10px]">Modo Local</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Interactive Panel (Asistentes) -->
                <div class="glass-card rounded-2xl p-5">
                    <h3 class="text-xs font-semibold uppercase tracking-wider opacity-70 mb-1">Panel Interactivo</h3>
                    <p class="text-xs opacity-50 mb-3">Disparar acciones para observar la reacción de la IA</p>
                    <div class="space-y-2">
                        <button id="ping-btn" class="w-full px-4 py-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/20 text-cyan-700 dark:text-[#00ffd5] text-sm font-medium transition-all flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/></svg>
                            Disparar Ping de Prueba
                        </button>
                        <button id="alert-btn" class="w-full px-4 py-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 text-amber-700 dark:text-[#ffd000] text-sm font-medium transition-all flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Activar Alerta Simulada
                        </button>
                    </div>
                </div>

                <!-- Instructor Panel -->
                <div class="glass-card rounded-2xl p-5">
                    <h3 class="text-xs font-semibold uppercase tracking-wider opacity-70 mb-1">Panel del Instructor</h3>
                    <p class="text-xs opacity-50 mb-3">Inyectar anomalías para demostración</p>
                    <div class="grid grid-cols-1 gap-2">
                        <button data-inject="high_temp" class="w-full px-3 py-2.5 rounded-xl bg-red-500/8 hover:bg-red-500/15 border border-red-500/20 text-red-600 dark:text-[#ff3366] text-sm font-medium transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4 4 0 1 0 5 0z"/></svg>
                            Alta Temperatura CPU
                        </button>
                        <button data-inject="cpu_overload" class="w-full px-3 py-2.5 rounded-xl bg-orange-500/8 hover:bg-orange-500/15 border border-orange-500/20 text-orange-600 dark:text-orange-400 text-sm font-medium transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M9 9h6v6H9z"/></svg>
                            Sobrecarga de CPU
                        </button>
                        <button data-inject="ram_leak" class="w-full px-3 py-2.5 rounded-xl bg-purple-500/8 hover:bg-purple-500/15 border border-purple-500/20 text-purple-600 dark:text-purple-400 text-sm font-medium transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 12h2M11 12h2M15 12h2"/></svg>
                            Fuga de Memoria RAM
                        </button>
                        <button data-inject="link_down" class="w-full px-3 py-2.5 rounded-xl bg-rose-500/8 hover:bg-rose-500/15 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-sm font-medium transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h4M18 12h4M8 12c2 0 2-4 4-4s2 4 4 4M8 12c2 0 2 4 4 4s2-4 4-4"/></svg>
                            Caída de Enlace Óptico
                        </button>
                        <button data-inject="network_congestion" class="w-full px-3 py-2.5 rounded-xl bg-yellow-500/8 hover:bg-yellow-500/15 border border-yellow-500/20 text-yellow-600 dark:text-[#ffd000] text-sm font-medium transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/></svg>
                            Congestión de Red
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center text-xs opacity-40">
            <p>FiberPulse AI · SENA IAoT Telemetry Dashboard · {{ now()->year }}</p>
            <p class="mt-1">Powered by Laravel 13 + Gemini 2.5 Flash + Docker + Redis + SSE</p>
        </footer>
    </main>

    <!-- Toast container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 w-80 max-w-[calc(100vw-2rem)] pointer-events-none [&>*]:pointer-events-auto"></div>

    <!-- ─── Data injection for JS ─── -->
    <script>
        window.FIBERPULSE_CONFIG = {{ \Illuminate\Support\Js::from([
            'localUrl'      => $localUrl,
            'geminiEnabled' => $geminiEnabled,
        ]) }};
        window.FIBERPULSE_DATA = {{ \Illuminate\Support\Js::from([
            'latest'   => $latest,
            'analysis' => $analysis,
            'anomaly'  => $anomaly,
            'history'  => $history,
        ]) }};
    </script>
</body>
</html>
