<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="FiberPulse AI - SENA IAoT Telemetry Dashboard">
    <title>FiberPulse AI · SENA IAoT Telemetry Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-[#0a0e17] grid-bg text-slate-800 dark:text-slate-200">

{{-- ════════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════ --}}
<nav class="sticky top-0 z-40 glass-card rounded-none border-x-0 border-t-0">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-14">

            {{-- Logo --}}
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-gradient-to-br from-[#00ffd5] to-[#a855f7]">
                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold gradient-text leading-none">FiberPulse AI</p>
                    <p class="text-[10px] uppercase tracking-widest opacity-40 leading-none mt-0.5">SENA IAoT Dashboard</p>
                </div>
            </div>

            {{-- Centro: estado del sistema (solo desktop) --}}
            <div class="hidden lg:flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span id="system-led" class="led led-normal led-pulse"></span>
                    <span id="system-status-text" class="text-xs font-bold uppercase tracking-wider text-green-600 dark:text-[#00ff88]">NORMAL</span>
                </div>
                <span class="text-slate-300 dark:text-slate-600">|</span>
                <span class="text-xs opacity-40">SSE:</span>
                <span id="sse-status" class="text-xs font-medium text-slate-500">Conectando...</span>
                <span class="text-slate-300 dark:text-slate-600">|</span>
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span id="user-count" class="text-xs font-bold tabular-nums">0</span>
                    <span class="text-xs opacity-40">usuarios</span>
                </div>
                <span class="text-slate-300 dark:text-slate-600">|</span>
                <span id="gemini-badge" class="status-badge bg-cyan-100 text-cyan-700 dark:bg-[#00ffd5]/15 dark:text-[#00ffd5]">Gemini</span>
            </div>

            {{-- Derecha: acciones --}}
            <div class="flex items-center space-x-3">
                <span id="dashboard-clock" class="hidden md:block text-xs font-mono tabular-nums opacity-40"></span>

                {{-- Botón Ver QR --}}
                <button id="btn-open-qr"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200
                           bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700
                           border border-slate-200 dark:border-slate-700
                           text-slate-700 dark:text-slate-200">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zm3 3h3v3h-3zm0-6h3v3h-3z"/>
                    </svg>
                    Ver QR
                </button>

                {{-- Botón Administrador (abre modal PIN) --}}
                <button id="btn-open-pin"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                           bg-violet-100 hover:bg-violet-200 dark:bg-violet-950/60 dark:hover:bg-violet-900/60
                           border border-violet-200 dark:border-violet-700/50
                           text-violet-700 dark:text-violet-300 transition-all duration-200">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <span class="hidden sm:inline">Administrador</span>
                </button>

                {{-- Toggle Dark / Light --}}
                <button id="theme-toggle" class="toggle-switch bg-slate-300 dark:bg-[#1e293b]" aria-label="Cambiar tema">
                    <span class="toggle-knob bg-white dark:bg-[#00ffd5] flex items-center justify-center">
                        <svg class="w-3 h-3 text-slate-600 dark:text-[#0a0e17]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4"/>
                            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                        </svg>
                    </span>
                </button>
            </div>

        </div>
    </div>
</nav>
{{-- /NAVBAR --}}


{{-- ════════════════════════════════════════════
     CONTENIDO PRINCIPAL
════════════════════════════════════════════ --}}
<div class="max-w-7xl mx-auto px-4 py-6">


    {{-- ──────────────────────────────────────
         FILA 1 · Banner Anomalía (condicional)
    ────────────────────────────────────────── --}}
    <div id="anomaly-banner" class="hidden w-full mb-4">
        <div class="glass-card rounded-2xl p-4 border border-red-300 dark:border-[#ff3366]/40 animate-critical-glow">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-[#ff3366]/15 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-red-600 dark:text-[#ff3366]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-semibold opacity-50">Anomalía Simulada Activa</p>
                        <p id="anomaly-label" class="text-sm font-bold text-red-600 dark:text-[#ff3366]">--</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span id="anomaly-countdown" class="text-sm font-mono tabular-nums opacity-60">--s</span>
                    <button id="clear-anomaly-btn"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold
                               bg-red-100 hover:bg-red-200 dark:bg-[#ff3366]/15 dark:hover:bg-[#ff3366]/25
                               border border-red-300 dark:border-[#ff3366]/40
                               text-red-700 dark:text-[#ff3366] transition-all duration-200">
                        Detener
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- /FILA 1 anomalía --}}


    {{-- ──────────────────────────────────────
         FILA 1 · Banner IA Gemini (ancho completo)
    ────────────────────────────────────────── --}}
    <div class="w-full mb-6">
        <div id="ai-banner" class="glass-card rounded-2xl p-5 border border-green-200 dark:border-[#00ff88]/30">
            <div class="flex flex-col md:flex-row md:items-start gap-4">

                {{-- Columna: estado IA --}}
                <div class="flex md:flex-col items-center md:items-start gap-3 md:gap-1.5 md:min-w-[160px] flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        </svg>
                        <span class="text-[10px] uppercase tracking-wider opacity-50 font-semibold">Análisis IA</span>
                    </div>
                    <span id="ai-status" class="status-badge bg-green-100 text-green-700 dark:bg-[#00ff88]/15 dark:text-[#00ff88]">
                        OPTIMAL
                    </span>
                    <span id="ai-source" class="text-[11px] font-medium text-cyan-600 dark:text-[#00ffd5]">
                        {{ $geminiModel }}
                    </span>
                </div>

                {{-- Divisor --}}
                <div class="hidden md:block w-px self-stretch bg-slate-200 dark:bg-slate-700/50"></div>

                {{-- Textos de diagnóstico --}}
                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider opacity-50 font-semibold mb-1">Diagnóstico Predictivo</p>
                        <p id="ai-predicted" class="text-sm leading-relaxed">
                            {{ $analysis['predicted_failure'] ?? 'Iniciando sistema de monitoreo. Esperando datos de telemetría...' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider opacity-50 font-semibold mb-1">Acción Recomendada</p>
                        <p id="ai-action" class="text-sm leading-relaxed opacity-75">
                            {{ $analysis['recommended_action'] ?? 'El collector de telemetría se activará automáticamente.' }}
                        </p>
                    </div>
                </div>

                {{-- Timestamp --}}
                <div class="flex-shrink-0 text-left md:text-right">
                    <p class="text-[10px] uppercase tracking-wider opacity-50 font-semibold mb-1">Actualizado</p>
                    <p id="ai-timestamp" class="text-sm font-mono tabular-nums">
                        {{ isset($analysis['timestamp'])
                            ? \Illuminate\Support\Carbon::parse($analysis['timestamp'])->format('H:i:s')
                            : '--:--:--' }}
                    </p>
                </div>

            </div>
        </div>
    </div>
    {{-- /FILA 1 IA --}}


    {{-- ──────────────────────────────────────
         FILA 2 · Tarjetas de Métricas (3 columnas × 2 filas = 6 tarjetas)
    ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        @php
            $metricDefs = [
                [
                    'key'       => 'cpu_usage',
                    'id'        => 'cpu',
                    'label'     => 'Uso de CPU',
                    'unit'      => '%',
                    'isPercent' => true,
                    'icon'      => '<path d="M4 4h16v16H4z"/><path d="M9 9h6v6H9z"/>',
                ],
                [
                    'key'       => 'ram_usage',
                    'id'        => 'ram',
                    'label'     => 'Uso de RAM',
                    'unit'      => '%',
                    'isPercent' => true,
                    'icon'      => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 12h2M11 12h2M15 12h2"/>',
                ],
                [
                    'key'       => 'temperature',
                    'id'        => 'temp',
                    'label'     => 'Temperatura',
                    'unit'      => '°C',
                    'isPercent' => true,
                    'icon'      => '<path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4 4 0 1 0 5 0z"/>',
                ],
                [
                    'key'       => 'network_throughput',
                    'id'        => 'net',
                    'label'     => 'Throughput',
                    'unit'      => 'Mbps',
                    'isPercent' => false,
                    'icon'      => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
                ],
                [
                    'key'       => 'latency_ms',
                    'id'        => 'latency',
                    'label'     => 'Latencia',
                    'unit'      => 'ms',
                    'isPercent' => false,
                    'icon'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                ],
                [
                    'key'       => 'optical_signal_dbm',
                    'id'        => 'optical',
                    'label'     => 'Señal Óptica',
                    'unit'      => 'dBm',
                    'isPercent' => false,
                    'icon'      => '<path d="M2 12h4M18 12h4M8 12c2 0 2-4 4-4s2 4 4 4M8 12c2 0 2 4 4 4s2-4 4-4"/>',
                ],
            ];
        @endphp

        @foreach ($metricDefs as $m)
        <div class="glass-card rounded-2xl p-5">
            {{-- Header de la tarjeta --}}
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 opacity-40" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        {!! $m['icon'] !!}
                    </svg>
                    <span class="text-xs font-semibold uppercase tracking-wider opacity-55">{{ $m['label'] }}</span>
                </div>
                <span id="metric-{{ $m['id'] }}-led" class="led led-normal"></span>
            </div>

            {{-- Valor --}}
            <div class="flex items-baseline gap-1 mb-3">
                <span id="metric-{{ $m['id'] }}-value"
                      class="text-4xl font-bold tabular-nums text-green-600 dark:text-[#00ff88] neon-green">
                    @if(isset($latest[$m['key']]))
                        {{ is_int($latest[$m['key']]) ? $latest[$m['key']] : number_format((float)$latest[$m['key']], 1) }}
                    @else
                        --
                    @endif
                </span>
                <span class="text-sm opacity-40">{{ $m['unit'] }}</span>
            </div>

            {{-- Barra de progreso (solo métricas de porcentaje) --}}
            @if ($m['isPercent'])
            <div class="w-full bg-slate-200 dark:bg-slate-700/40 rounded-full h-1.5 overflow-hidden">
                <div id="metric-{{ $m['id'] }}-bar"
                     class="h-full rounded-full bg-green-500 dark:bg-[#00ff88] transition-all duration-500"
                     style="width: {{ min((float)($latest[$m['key']] ?? 0), 100) }}%">
                </div>
            </div>
            @endif
        </div>
        @endforeach

    </div>
    {{-- /FILA 2 métricas --}}


    {{-- ──────────────────────────────────────
         FILA 3 · Gráficas en tiempo real (2 columnas)
    ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        @php
            $chartDefs = [
                ['id' => 'cpu',  'label' => 'CPU (%)'],
                ['id' => 'ram',  'label' => 'RAM (%)'],
                ['id' => 'temp', 'label' => 'Temperatura (°C)'],
                ['id' => 'net',  'label' => 'Throughput (Mbps)'],
            ];
        @endphp

        @foreach ($chartDefs as $c)
        <div class="glass-card rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-semibold uppercase tracking-wider opacity-55">{{ $c['label'] }}</h3>
                <span class="led led-normal"></span>
            </div>
            <div class="chart-container">
                <canvas id="chart-{{ $c['id'] }}"></canvas>
            </div>
        </div>
        @endforeach

    </div>
    {{-- /FILA 3 gráficas --}}


    {{-- ──────────────────────────────────────
         FOOTER INFO BAR
    ────────────────────────────────────────── --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-4 text-xs">
            <div class="flex items-center gap-1.5">
                <span class="opacity-40">Usuarios:</span>
                <span class="led led-normal led-pulse"></span>
                <span id="user-count-side" class="font-bold tabular-nums">{{ $userCount ?? 0 }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="opacity-40">SSE:</span>
                <span id="sse-status-side" class="font-medium text-slate-500">Conectando...</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="opacity-40">Motor IA:</span>
                @if($geminiEnabled)
                    <span class="status-badge bg-cyan-100 text-cyan-700 dark:bg-[#00ffd5]/15 dark:text-[#00ffd5] text-[10px]">Gemini Activo</span>
                @else
                    <span class="status-badge bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 text-[10px]">Modo Local</span>
                @endif
            </div>
        </div>
        <p class="text-xs opacity-25">
            FiberPulse AI · SENA IAoT · Laravel 13 + Gemini + Docker + Redis + SSE · {{ now()->year }}
        </p>
    </div>

</div>
{{-- /CONTENIDO PRINCIPAL --}}


{{-- ════════════════════════════════════════════
     MODAL · Código QR
════════════════════════════════════════════ --}}
<div id="modal-qr" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div id="modal-qr-backdrop"
         class="modal-backdrop absolute inset-0 bg-black/60 backdrop-blur-sm">
    </div>

    {{-- Panel --}}
    <div class="modal-panel relative z-10 glass-card rounded-3xl p-8 w-full max-w-sm text-center shadow-2xl">

        <button id="btn-close-qr"
            class="absolute top-4 right-4 w-8 h-8 rounded-full flex items-center justify-center
                   bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600
                   transition-all duration-200">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <h2 class="text-sm font-bold uppercase tracking-wider opacity-60 mb-5">Conexión Rápida</h2>

        <div class="flex justify-center mb-4">
            <div class="p-3 bg-white rounded-2xl shadow-lg inline-block" id="qr-wrapper">
                <canvas id="qr-canvas"></canvas>
            </div>
        </div>

        <p class="local-url-text text-sm font-mono font-medium opacity-60 break-all mb-4">
            {{ $localUrl }}
        </p>

        <button id="copy-url-btn"
            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                   bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/50 dark:hover:bg-slate-700
                   border border-slate-200 dark:border-slate-600
                   text-sm font-semibold transition-all duration-200 mb-3">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
            Copiar URL
        </button>

        <p class="text-xs opacity-30">Escanea este QR para abrir el dashboard en tu celular</p>

    </div>
</div>
{{-- /MODAL QR --}}


{{-- ════════════════════════════════════════════
     MODAL · PIN de acceso (Administrador)
════════════════════════════════════════════ --}}
<div id="modal-pin" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div id="modal-pin-backdrop" class="modal-backdrop absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

    {{-- Panel --}}
    <div class="modal-panel relative z-10 glass-card rounded-3xl p-8 w-full max-w-xs text-center shadow-2xl">

        <button id="btn-close-pin"
            class="absolute top-4 right-4 w-8 h-8 rounded-full flex items-center justify-center
                   bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600
                   transition-all duration-200">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        {{-- Icono --}}
        <div class="w-14 h-14 rounded-2xl bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-violet-600 dark:text-violet-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <h2 class="text-sm font-bold uppercase tracking-wider mb-1">Acceso Administrador</h2>
        <p class="text-xs opacity-45 mb-6">Ingresa el PIN de 4 dígitos para continuar</p>

        {{-- Input PIN --}}
        <div class="relative mb-3">
            <input
                id="pin-input"
                type="password"
                inputmode="numeric"
                maxlength="4"
                placeholder="• • • •"
                autocomplete="off"
                class="w-full text-center text-2xl font-bold tracking-[0.5em] py-3 px-4 rounded-xl
                       bg-slate-100 dark:bg-slate-800
                       border-2 border-slate-200 dark:border-slate-700
                       focus:outline-none focus:border-violet-500 dark:focus:border-violet-400
                       text-slate-800 dark:text-slate-100
                       transition-all duration-200"
            >
        </div>

        {{-- Mensaje de error --}}
        <p id="pin-error" class="hidden text-xs font-semibold text-red-500 dark:text-[#ff3366] mb-3">
            PIN incorrecto. Inténtalo de nuevo.
        </p>

        {{-- Botón confirmar --}}
        <button id="btn-confirm-pin"
            class="w-full py-2.5 rounded-xl text-sm font-bold
                   bg-violet-600 hover:bg-violet-700 dark:bg-violet-700 dark:hover:bg-violet-600
                   text-white transition-all duration-200 shadow-lg">
            Acceder
        </button>

    </div>
</div>
{{-- /MODAL PIN --}}


{{-- ════════════════════════════════════════════
     MODAL · Panel del Administrador
════════════════════════════════════════════ --}}
<div id="modal-instructor" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4">

    {{-- Backdrop --}}
    <div id="modal-instructor-backdrop"
         class="modal-backdrop absolute inset-0 bg-black/60 backdrop-blur-sm">
    </div>

    {{-- Panel --}}
    <div class="modal-panel relative z-10 glass-card rounded-3xl p-6 w-full max-w-md shadow-2xl">

        {{-- Header del modal --}}
        <div class="flex items-start justify-between mb-5">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider">Panel del Administrador</h2>
                <p class="text-xs opacity-45 mt-0.5">Inyecta anomalías para demostración en tiempo real</p>
            </div>
            <button id="btn-close-instructor"
                class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0
                       bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600
                       transition-all duration-200">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- Botones de anomalías --}}
        <div class="space-y-2">

            <button data-inject="high_temp"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                       bg-red-500/8 hover:bg-red-500/15 border border-red-400/25
                       text-red-600 dark:text-[#ff3366] transition-all duration-200">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4 4 0 1 0 5 0z"/>
                </svg>
                Alta Temperatura de CPU
            </button>

            <button data-inject="cpu_overload"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                       bg-orange-500/8 hover:bg-orange-500/15 border border-orange-400/25
                       text-orange-600 dark:text-orange-400 transition-all duration-200">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16v16H4z"/><path d="M9 9h6v6H9z"/>
                </svg>
                Sobrecarga de CPU
            </button>

            <button data-inject="ram_leak"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                       bg-purple-500/8 hover:bg-purple-500/15 border border-purple-400/25
                       text-purple-600 dark:text-purple-400 transition-all duration-200">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="6" width="18" height="12" rx="2"/>
                    <path d="M7 12h2M11 12h2M15 12h2"/>
                </svg>
                Fuga de Memoria RAM
            </button>

            <button data-inject="link_down"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                       bg-rose-500/8 hover:bg-rose-500/15 border border-rose-400/25
                       text-rose-600 dark:text-rose-400 transition-all duration-200">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 12h4M18 12h4M8 12c2 0 2-4 4-4s2 4 4 4M8 12c2 0 2 4 4 4s2-4 4-4"/>
                </svg>
                Caída de Enlace Óptico
            </button>

            <button data-inject="network_congestion"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
                       bg-yellow-500/8 hover:bg-yellow-500/15 border border-yellow-400/25
                       text-yellow-600 dark:text-[#ffd000] transition-all duration-200">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
                    <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                </svg>
                Congestión de Red
            </button>

        </div>

        {{-- Divisor + botón restablecer --}}
        <div class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-700/50">
            <button id="clear-anomaly-btn-modal"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                       text-sm font-semibold transition-all duration-200
                       bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/50 dark:hover:bg-slate-700
                       border border-slate-200 dark:border-slate-600">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="1 4 1 10 7 10"/>
                    <path d="M3.51 15a9 9 0 1 0 .49-3.96"/>
                </svg>
                Restablecer sistema a normal
            </button>
        </div>

    </div>
</div>
{{-- /MODAL INSTRUCTOR --}}


{{-- Toast container --}}
<div id="toast-container"
     class="fixed bottom-4 right-4 z-[60] w-80 max-w-[calc(100vw-2rem)]
            pointer-events-none [&>*]:pointer-events-auto">
</div>


{{-- ─── Datos para JS (Vite) ─── --}}
<script>
    window.FIBERPULSE_CONFIG = {{ \Illuminate\Support\Js::from([
        'localUrl'      => $localUrl,
        'geminiEnabled' => $geminiEnabled,
        'geminiModel'   => $geminiModel,
    ]) }};
    window.FIBERPULSE_DATA = {{ \Illuminate\Support\Js::from([
        'latest'   => $latest,
        'analysis' => $analysis,
        'anomaly'  => $anomaly,
        'history'  => $history,
    ]) }};
</script>

{{-- ─── Script inline: lógica de modales y PIN (no depende de Vite/build) ─── --}}
<script>
(function () {
    'use strict';

    var ADMIN_PIN      = '0117';
    var SESSION_KEY    = 'fiberpulse_admin_auth';

    /* ── helpers de modal ── */
    function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('hidden');
        requestAnimationFrame(function () { m.classList.add('modal-open'); });
    }

    function closeModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('modal-open');
        setTimeout(function () { m.classList.add('hidden'); }, 250);
    }

    /* ── PIN ── */
    function isAuth() { return sessionStorage.getItem(SESSION_KEY) === 'true'; }
    function setAuth() { sessionStorage.setItem(SESSION_KEY, 'true'); }

    function verifyPin() {
        var input = document.getElementById('pin-input');
        var error = document.getElementById('pin-error');
        if (!input) return;
        if (input.value.trim() === ADMIN_PIN) {
            setAuth();
            if (error) error.classList.add('hidden');
            input.value = '';
            closeModal('modal-pin');
            setTimeout(function () { openModal('modal-instructor'); }, 280);
        } else {
            input.value = '';
            if (error) error.classList.remove('hidden');
            setTimeout(function () {
                if (error) error.classList.add('hidden');
            }, 2500);
            input.focus();
        }
    }

    /* ── Toast simple ── */
    function toast(msg, type) {
        var c = document.getElementById('toast-container');
        if (!c) return;
        var isDark = document.documentElement.classList.contains('dark');
        var colors = {
            warning: isDark ? 'border-yellow-400/40 text-yellow-300' : 'border-yellow-300 text-yellow-700',
            success: isDark ? 'border-green-400/40 text-green-300'   : 'border-green-300 text-green-700',
            error:   isDark ? 'border-red-400/40 text-red-300'       : 'border-red-300 text-red-700',
            info:    isDark ? 'border-white/10 text-slate-200'        : 'border-slate-200 text-slate-700',
        };
        var t = document.createElement('div');
        t.className = 'glass-card px-4 py-3 rounded-xl border mb-2 text-sm font-medium animate-fade-in ' + (colors[type] || colors.info);
        t.textContent = msg;
        c.appendChild(t);
        setTimeout(function () {
            t.style.cssText += 'opacity:0;transform:translateY(-8px);transition:all 0.3s ease;';
            setTimeout(function () { t.remove(); }, 300);
        }, 3500);
    }

    /* ── Inyectar anomalía (AJAX) ── */
    function injectAnomaly(type, btn) {
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Inyectando...';
        fetch('/telemetry/inject/' + type, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ duration: 60 })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            toast((d.success ? '⚡ ' : '❌ ') + d.message, d.success ? 'warning' : 'error');
        })
        .catch(function () { toast('Error de conexión', 'error'); })
        .finally(function () {
            setTimeout(function () { btn.disabled = false; btn.innerHTML = orig; }, 2000);
        });
    }

    function clearAnomaly() {
        fetch('/telemetry/clear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({})
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { toast((d.success ? '✅ ' : '❌ ') + d.message, d.success ? 'success' : 'error'); })
        .catch(function () { toast('Error de conexión', 'error'); });
    }

    /* ── Wire-up al cargar el DOM ── */
    document.addEventListener('DOMContentLoaded', function () {

        /* Botón Administrador */
        var btnAdmin = document.getElementById('btn-open-pin');
        if (btnAdmin) {
            btnAdmin.addEventListener('click', function () {
                if (isAuth()) { openModal('modal-instructor'); }
                else {
                    openModal('modal-pin');
                    setTimeout(function () {
                        var inp = document.getElementById('pin-input');
                        if (inp) inp.focus();
                    }, 300);
                }
            });
        }

        /* Modal PIN */
        document.getElementById('btn-close-pin')?.addEventListener('click', function () {
            document.getElementById('pin-input').value = '';
            document.getElementById('pin-error')?.classList.add('hidden');
            closeModal('modal-pin');
        });
        document.getElementById('modal-pin-backdrop')?.addEventListener('click', function () {
            document.getElementById('pin-input').value = '';
            document.getElementById('pin-error')?.classList.add('hidden');
            closeModal('modal-pin');
        });
        document.getElementById('btn-confirm-pin')?.addEventListener('click', verifyPin);
        document.getElementById('pin-input')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') verifyPin();
        });

        /* Modal Instructor */
        document.getElementById('btn-close-instructor')?.addEventListener('click', function () { closeModal('modal-instructor'); });
        document.getElementById('modal-instructor-backdrop')?.addEventListener('click', function () { closeModal('modal-instructor'); });

        /* Botones de anomalía */
        document.querySelectorAll('[data-inject]').forEach(function (btn) {
            btn.addEventListener('click', function () { injectAnomaly(btn.dataset.inject, btn); });
        });

        /* Detener anomalía */
        document.getElementById('clear-anomaly-btn')?.addEventListener('click', clearAnomaly);
        document.getElementById('clear-anomaly-btn-modal')?.addEventListener('click', function () {
            clearAnomaly();
            closeModal('modal-instructor');
        });

        /* Botón QR */
        document.getElementById('btn-open-qr')?.addEventListener('click', function () {
            openModal('modal-qr');
        });
        document.getElementById('btn-close-qr')?.addEventListener('click', function () { closeModal('modal-qr'); });
        document.getElementById('modal-qr-backdrop')?.addEventListener('click', function () { closeModal('modal-qr'); });

        /* Copiar URL */
        document.getElementById('copy-url-btn')?.addEventListener('click', function () {
            var url = document.querySelector('.local-url-text')?.textContent?.trim() || '';
            if (url) navigator.clipboard.writeText(url).then(function () { toast('URL copiada', 'success'); });
        });

        /* Escape cierra cualquier modal */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                ['modal-qr', 'modal-pin', 'modal-instructor'].forEach(closeModal);
            }
        });
    });
})();
</script>

</body>
</html>
