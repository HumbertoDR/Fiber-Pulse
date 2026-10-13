import Chart from 'chart.js/auto';
import QRCode from 'qrcode';

/**
 * FiberPulse AI - Dashboard Frontend
 * ---------------------------------
 * SSE, gráficos en tiempo real, QR code, dark/light mode,
 * interacciones de asistentes e instructor.
 */

// ─── Config inyectada desde Blade ───
const CONFIG = window.FIBERPULSE_CONFIG || {};
const INITIAL = window.FIBERPULSE_DATA || {};

// ─── Estado global ───
const state = {
    telemetry: INITIAL.latest || null,
    analysis: INITIAL.analysis || null,
    anomaly: INITIAL.anomaly || null,
    userCount: 0,
    charts: {},
    anomalyTimer: null,
};

// ─── Definición de métricas ───
const METRICS = {
    cpu_usage: {
        id: 'cpu', label: 'Uso de CPU', icon: 'cpu', unit: '%',
        thresholds: { warning: 70, critical: 85 }, max: 100, isPercent: true
    },
    ram_usage: {
        id: 'ram', label: 'Uso de RAM', icon: 'ram', unit: '%',
        thresholds: { warning: 75, critical: 85 }, max: 100, isPercent: true
    },
    temperature: {
        id: 'temp', label: 'Temperatura', icon: 'thermo', unit: '°C',
        thresholds: { warning: 70, critical: 80 }, max: 100, isPercent: true
    },
    network_throughput: {
        id: 'net', label: 'Throughput', icon: 'network', unit: 'Mbps',
        thresholds: { warning: 800, critical: 950 }, max: null, isPercent: false
    },
    latency_ms: {
        id: 'latency', label: 'Latencia', icon: 'latency', unit: 'ms',
        thresholds: { warning: 100, critical: 200 }, max: null, isPercent: false
    },
    optical_signal_dbm: {
        id: 'optical', label: 'Señal Óptica', icon: 'fiber', unit: 'dBm',
        thresholds: { warning: -25, critical: -35 }, max: null, isPercent: false, inverted: true
    },
};

const CHART_COLORS = {
    cpu: { dark: '#00ffd5', light: '#0891b2' },
    ram: { dark: '#a855f7', light: '#7c3aed' },
    temp: { dark: '#ff3366', light: '#dc2626' },
    net: { dark: '#00ff88', light: '#16a34a' },
};

// ═══════════════════════════════════════════════
//  TEMA: Dark / Light Mode
// ═══════════════════════════════════════════════

function getStoredTheme() {
    return localStorage.getItem('fiberpulse-theme') ||
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
}

function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    localStorage.setItem('fiberpulse-theme', theme);
    updateChartsTheme();
    // Re-generate QR con colores del tema
    if (CONFIG.localUrl) generateQRCode();
}

function toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    applyTheme(isDark ? 'light' : 'dark');
}

// ═══════════════════════════════════════════════
//  GRÁFICOS: Chart.js en tiempo real
// ═══════════════════════════════════════════════

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function getChartColor(metric) {
    const c = CHART_COLORS[metric] || CHART_COLORS.cpu;
    return isDark() ? c.dark : c.light;
}

function getGridColor() {
    return isDark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
}

function getTickColor() {
    return isDark() ? 'rgba(255,255,255,0.4)' : 'rgba(0,0,0,0.4)';
}

function createChart(canvasId, metricKey, yMax) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    const color = getChartColor(metricKey);

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                data: [],
                borderColor: color,
                backgroundColor: hexToRgba(color, 0.12),
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 0 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark() ? 'rgba(15,23,42,0.95)' : 'rgba(255,255,255,0.95)',
                    titleColor: isDark() ? '#e2e8f0' : '#1e293b',
                    bodyColor: isDark() ? '#94a3b8' : '#64748b',
                    borderColor: isDark() ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                },
            },
            scales: {
                x: {
                    display: false,
                    grid: { color: getGridColor() },
                },
                y: {
                    min: 0,
                    max: yMax || undefined,
                    grid: { color: getGridColor() },
                    ticks: {
                        color: getTickColor(),
                        font: { size: 9 },
                        maxTicksLimit: 4,
                    },
                },
            },
        },
    });
}

function initCharts() {
    state.charts.cpu = createChart('chart-cpu', 'cpu', 100);
    state.charts.ram = createChart('chart-ram', 'ram', 100);
    state.charts.temp = createChart('chart-temp', 'temp', 100);
    state.charts.net = createChart('chart-net', 'net', null);

    // Cargar datos iniciales del historial
    if (INITIAL.history && INITIAL.history.length > 0) {
        INITIAL.history.forEach((point) => {
            pushChartPoint(point);
        });
        Object.values(state.charts).forEach(c => c && c.update('none'));
    }
}

function pushChartPoint(data) {
    const time = (data.recorded_at || new Date().toISOString()).slice(11, 19);

    if (state.charts.cpu) {
        state.charts.cpu.data.labels.push(time);
        state.charts.cpu.data.datasets[0].data.push(data.cpu_usage);
        trimChart(state.charts.cpu);
    }
    if (state.charts.ram) {
        state.charts.ram.data.labels.push(time);
        state.charts.ram.data.datasets[0].data.push(data.ram_usage);
        trimChart(state.charts.ram);
    }
    if (state.charts.temp) {
        state.charts.temp.data.labels.push(time);
        state.charts.temp.data.datasets[0].data.push(data.temperature);
        trimChart(state.charts.temp);
    }
    if (state.charts.net) {
        state.charts.net.data.labels.push(time);
        state.charts.net.data.datasets[0].data.push(data.network_throughput);
        trimChart(state.charts.net);
    }
}

function trimChart(chart, maxPoints = 30) {
    if (chart.data.labels.length > maxPoints) {
        chart.data.labels.shift();
        chart.data.datasets[0].data.shift();
    }
}

function updateChartsTheme() {
    const grid = getGridColor();
    const tick = getTickColor();

    const metricKeys = { cpu: 'cpu', ram: 'ram', temp: 'temp', net: 'net' };
    Object.entries(state.charts).forEach(([key, chart]) => {
        if (!chart) return;
        const color = getChartColor(metricKeys[key]);
        chart.data.datasets[0].borderColor = color;
        chart.data.datasets[0].backgroundColor = hexToRgba(color, 0.12);
        chart.options.scales.x.grid.color = grid;
        chart.options.scales.y.grid.color = grid;
        chart.options.scales.y.ticks.color = tick;
        chart.options.plugins.tooltip.backgroundColor = isDark() ? 'rgba(15,23,42,0.95)' : 'rgba(255,255,255,0.95)';
        chart.options.plugins.tooltip.titleColor = isDark() ? '#e2e8f0' : '#1e293b';
        chart.options.plugins.tooltip.bodyColor = isDark() ? '#94a3b8' : '#64748b';
        chart.update('none');
    });
}

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

// ═══════════════════════════════════════════════
//  TARJETAS DE MÉTRICAS
// ═══════════════════════════════════════════════

function getMetricStatus(key, value) {
    const m = METRICS[key];
    if (!m) return 'normal';

    if (m.inverted) {
        if (value <= m.thresholds.critical) return 'critical';
        if (value <= m.thresholds.warning) return 'warning';
    } else {
        if (value >= m.thresholds.critical) return 'critical';
        if (value >= m.thresholds.warning) return 'warning';
    }
    return 'normal';
}

function updateMetrics(data) {
    Object.entries(METRICS).forEach(([key, cfg]) => {
        const value = data[key];
        if (value === undefined || value === null) return;

        const status = getMetricStatus(key, value);

        // Valor
        const valEl = document.getElementById(`metric-${cfg.id}-value`);
        if (valEl) {
            valEl.textContent = Number.isInteger(value) ? value : value.toFixed(1);
        }

        // LED
        const ledEl = document.getElementById(`metric-${cfg.id}-led`);
        if (ledEl) {
            ledEl.className = `led led-${status} ${status !== 'normal' ? 'led-pulse' : ''}`;
        }

        // Barra de progreso (solo para porcentajes)
        if (cfg.isPercent) {
            const barEl = document.getElementById(`metric-${cfg.id}-bar`);
            if (barEl) {
                barEl.style.width = `${Math.min(value, 100)}%`;
                barEl.className = `h-full rounded-full transition-all duration-500 ${getBarColorClass(status)}`;
            }
        }

        // Color del valor según estado
        if (valEl) {
            valEl.className = `text-3xl font-bold ${getValueColorClass(status)}`;
        }
    });

    // Estado global del sistema en la barra superior
    updateSystemStatus(data.status);
}

function updateSystemStatus(status) {
    const led = document.getElementById('system-led');
    const text = document.getElementById('system-status-text');
    if (!led || !text) return;

    led.className = `led led-${status} ${status !== 'normal' ? 'led-pulse' : ''}`;
    text.textContent = status.toUpperCase();
    text.className = `text-xs font-bold uppercase tracking-wider ${getValueColorClass(status)}`;
}

function getBarColorClass(status) {
    if (isDark()) {
        switch (status) {
            case 'critical': return 'bg-[#ff3366] shadow-[0_0_8px_rgba(255,51,102,0.5)]';
            case 'warning':  return 'bg-[#ffd000] shadow-[0_0_8px_rgba(255,208,0,0.5)]';
            default:         return 'bg-[#00ff88] shadow-[0_0_8px_rgba(0,255,136,0.4)]';
        }
    } else {
        switch (status) {
            case 'critical': return 'bg-red-500';
            case 'warning':  return 'bg-yellow-500';
            default:         return 'bg-green-500';
        }
    }
}

function getValueColorClass(status) {
    if (isDark()) {
        switch (status) {
            case 'critical': return 'text-[#ff3366] neon-red';
            case 'warning':  return 'text-[#ffd000] neon-yellow';
            default:         return 'text-[#00ff88] neon-green';
        }
    } else {
        switch (status) {
            case 'critical': return 'text-red-600';
            case 'warning':  return 'text-yellow-600';
            default:         return 'text-green-600';
        }
    }
}

// ═══════════════════════════════════════════════
//  BANNER DE IA (Gemini)
// ═══════════════════════════════════════════════

function updateAIBanner(data) {
    state.analysis = data;
    const banner = document.getElementById('ai-banner');
    if (!banner || !data) return;

    const status = (data.status || 'OPTIMAL').toUpperCase();
    const statusClass = statusToClass(status);

    // Actualizar clases del banner
    banner.className = `glass-card rounded-2xl p-5 animate-banner-flash ${statusClass.border}`;

    // Status badge
    const statusEl = document.getElementById('ai-status');
    if (statusEl) {
        statusEl.textContent = status;
        statusEl.className = `status-badge ${statusClass.badge}`;
    }

    // Texto de predicción
    const predEl = document.getElementById('ai-predicted');
    if (predEl) predEl.textContent = data.predicted_failure || 'Sin diagnóstico disponible.';

    // Texto de acción recomendada
    const actionEl = document.getElementById('ai-action');
    if (actionEl) actionEl.textContent = data.recommended_action || 'Continuar monitoreo.';

    // Fuente (Gemini vs threshold)
    const sourceEl = document.getElementById('ai-source');
    if (sourceEl) {
        const source = data.source || 'unknown';
        sourceEl.textContent = source === 'gemini' ? 'Gemini 2.5 Flash' : 'Análisis Local (umbrales)';
        sourceEl.className = `text-xs font-medium ${source === 'gemini' ? 'text-neon-cyan' : 'text-slate-500 dark:text-slate-400'}`;
    }

    // Timestamp
    const timeEl = document.getElementById('ai-timestamp');
    if (timeEl) timeEl.textContent = formatTime(data.timestamp);

    // Glow effect según estado
    banner.style.boxShadow = status === 'CRITICAL'
        ? (isDark() ? '0 0 24px rgba(255,51,102,0.3), 0 4px 24px rgba(0,0,0,0.4)' : '0 0 24px rgba(220,38,38,0.2), 0 4px 24px rgba(0,0,0,0.06)')
        : status === 'WARNING'
            ? (isDark() ? '0 0 24px rgba(255,208,0,0.2), 0 4px 24px rgba(0,0,0,0.4)' : '0 0 24px rgba(202,138,4,0.15), 0 4px 24px rgba(0,0,0,0.06)')
            : '';
}

function statusToClass(status) {
    const map = {
        OPTIMAL: {
            border: isDark() ? 'border-[#00ff88]/30' : 'border-green-200',
            badge: isDark() ? 'bg-[#00ff88]/15 text-[#00ff88]' : 'bg-green-100 text-green-700',
        },
        WARNING: {
            border: isDark() ? 'border-[#ffd000]/30' : 'border-yellow-200',
            badge: isDark() ? 'bg-[#ffd000]/15 text-[#ffd000]' : 'bg-yellow-100 text-yellow-700',
        },
        CRITICAL: {
            border: isDark() ? 'border-[#ff3366]/30' : 'border-red-200',
            badge: isDark() ? 'bg-[#ff3366]/15 text-[#ff3366]' : 'bg-red-100 text-red-700',
        },
    };
    return map[status] || map.OPTIMAL;
}

// ═══════════════════════════════════════════════
//  BANNER DE ANOMALÍA
// ═══════════════════════════════════════════════

function updateAnomaly(data) {
    state.anomaly = data;
    const banner = document.getElementById('anomaly-banner');

    if (!banner) return;

    if (!data) {
        banner.classList.add('hidden');
        if (state.anomalyTimer) {
            clearInterval(state.anomalyTimer);
            state.anomalyTimer = null;
        }
        return;
    }

    banner.classList.remove('hidden');
    banner.classList.add('animate-fade-in');

    const labelEl = document.getElementById('anomaly-label');
    if (labelEl) labelEl.textContent = data.type || 'Anomalía activa';

    // Countdown timer
    updateAnomalyCountdown(data);
    if (state.anomalyTimer) clearInterval(state.anomalyTimer);
    state.anomalyTimer = setInterval(() => updateAnomalyCountdown(data), 1000);
}

function updateAnomalyCountdown(data) {
    const remaining = Math.max(0, (data.duration || 0) - Math.floor((Date.now() / 1000) - (data.started_at || Date.now() / 1000)));
    const el = document.getElementById('anomaly-countdown');
    if (el) {
        el.textContent = `${remaining}s restantes`;
    }
    if (remaining <= 0) {
        clearInterval(state.anomalyTimer);
        state.anomalyTimer = null;
    }
}

// ═══════════════════════════════════════════════
//  QR CODE
// ═══════════════════════════════════════════════

function generateQRCode() {
    const canvas = document.getElementById('qr-canvas');
    if (!canvas || !CONFIG.localUrl) return;

    QRCode.toCanvas(canvas, CONFIG.localUrl, {
        width: 220,
        margin: 2,
        errorCorrectionLevel: 'M',
        color: {
            dark: '#0a0e17',
            light: '#ffffff',
        },
    }, (err) => {
        if (err) console.error('Error generando QR:', err);
    });
}

// ═══════════════════════════════════════════════
//  SSE: Server-Sent Events
// ═══════════════════════════════════════════════

function initSSE() {
    setSSEStatus('Conectando...', 'yellow');

    const evtSource = new EventSource('/telemetry/stream');

    evtSource.addEventListener('open', () => {
        setSSEStatus('Conectado', 'green');
    });

    evtSource.addEventListener('telemetry', (e) => {
        try {
            const data = JSON.parse(e.data);
            state.telemetry = data;
            updateMetrics(data);
            pushChartPoint(data);
            Object.values(state.charts).forEach(c => c && c.update('none'));
        } catch (err) {
            console.error('SSE telemetry error:', err);
        }
    });

    evtSource.addEventListener('analysis', (e) => {
        try {
            const data = JSON.parse(e.data);
            updateAIBanner(data);
        } catch (err) {
            console.error('SSE analysis error:', err);
        }
    });

    evtSource.addEventListener('anomaly', (e) => {
        try {
            const data = e.data === 'null' ? null : JSON.parse(e.data);
            updateAnomaly(data);
        } catch (err) {
            updateAnomaly(null);
        }
    });

    evtSource.addEventListener('connections', (e) => {
        try {
            const data = JSON.parse(e.data);
            document.querySelectorAll('#user-count, #user-count-side').forEach(el => {
                el.textContent = data.count;
            });
        } catch (err) { /* ignore */ }
    });

    evtSource.onerror = () => {
        setSSEStatus('Reconectando...', 'yellow');
        // EventSource auto-reconecta
    };
}

function setSSEStatus(text, color) {
    const cls = color === 'green'
        ? 'text-xs font-medium text-green-600 dark:text-[#00ffd5]'
        : 'text-xs font-medium text-yellow-600 dark:text-[#ffd000]';
    document.querySelectorAll('#sse-status, #sse-status-side').forEach(el => {
        el.textContent = text;
        el.className = cls;
    });
}

// ═══════════════════════════════════════════════
//  POST: Fetch helper
// ═══════════════════════════════════════════════

async function postJSON(url, body = {}) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        return await res.json();
    } catch (err) {
        console.error('POST error:', err);
        return { success: false, message: 'Error de conexión' };
    }
}

// ═══════════════════════════════════════════════
//  TOAST: Notificaciones
// ═══════════════════════════════════════════════

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const styles = {
        success: isDark() ? 'border-[#00ff88]/30 text-[#00ff88]' : 'border-green-200 text-green-700',
        warning: isDark() ? 'border-[#ffd000]/30 text-[#ffd000]' : 'border-yellow-200 text-yellow-700',
        error:   isDark() ? 'border-[#ff3366]/30 text-[#ff3366]' : 'border-red-200 text-red-700',
        info:    isDark() ? 'border-white/10 text-slate-200' : 'border-slate-200 text-slate-700',
    };

    const toast = document.createElement('div');
    toast.className = `toast-enter glass-card px-4 py-3 rounded-xl border ${styles[type] || styles.info} text-sm font-medium mb-2`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-8px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ═══════════════════════════════════════════════
//  BOTONES: Asistentes + Instructor
// ═══════════════════════════════════════════════

function initButtons() {
    // Theme toggle
    document.getElementById('theme-toggle')?.addEventListener('click', toggleTheme);

    // Ping (asistentes)
    document.getElementById('ping-btn')?.addEventListener('click', async (e) => {
        e.currentTarget.classList.add('animate-ripple');
        setTimeout(() => e.currentTarget.classList.remove('animate-ripple'), 800);
        const result = await postJSON('/telemetry/ping');
        showToast(`Ping: ${result.message}`, 'success');
    });

    // Alerta (asistentes)
    document.getElementById('alert-btn')?.addEventListener('click', async () => {
        const result = await postJSON('/telemetry/alert');
        showToast(result.message, 'warning');
    });

    // Inyectar anomalías (instructor)
    document.querySelectorAll('[data-inject]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const type = btn.dataset.inject;
            btn.disabled = true;
            const result = await postJSON(`/telemetry/inject/${type}`, { duration: 60 });
            showToast(result.message, 'warning');
            setTimeout(() => { btn.disabled = false; }, 2000);
        });
    });

    // Limpiar anomalía
    document.getElementById('clear-anomaly-btn')?.addEventListener('click', async () => {
        const result = await postJSON('/telemetry/clear');
        showToast(result.message, 'success');
    });

    // Copiar URL
    document.getElementById('copy-url-btn')?.addEventListener('click', () => {
        navigator.clipboard.writeText(CONFIG.localUrl || '').then(() => {
            showToast('URL copiada al portapapeles', 'success');
        });
    });
}

// ═══════════════════════════════════════════════
//  UTILS
// ═══════════════════════════════════════════════

function formatTime(ts) {
    if (!ts) return '';
    try {
        const d = new Date(ts);
        return d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return '';
    }
}

// ═══════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // Tema inicial
    applyTheme(getStoredTheme());

    // Gráficos
    initCharts();

    // QR code
    generateQRCode();

    // Mostrar URL local
    const urlEl = document.getElementById('local-url-text');
    if (urlEl && CONFIG.localUrl) urlEl.textContent = CONFIG.localUrl;

    // Estado de Gemini
    const geminiBadge = document.getElementById('gemini-badge');
    if (geminiBadge) {
        if (CONFIG.geminiEnabled) {
            geminiBadge.textContent = 'Gemini 2.5 Flash';
            geminiBadge.className = 'status-badge ' + (isDark() ? 'bg-[#00ffd5]/15 text-[#00ffd5]' : 'bg-cyan-100 text-cyan-700');
        } else {
            geminiBadge.textContent = 'Análisis Local';
            geminiBadge.className = 'status-badge ' + (isDark() ? 'bg-slate-700 text-slate-300' : 'bg-slate-200 text-slate-600');
        }
    }

    // Datos iniciales
    if (state.telemetry) updateMetrics(state.telemetry);
    if (state.analysis) updateAIBanner(state.analysis);
    if (state.anomaly) updateAnomaly(state.anomaly);

    // SSE + Botones
    initSSE();
    initButtons();

    // Reloj del dashboard
    setInterval(() => {
        const el = document.getElementById('dashboard-clock');
        if (el) el.textContent = new Date().toLocaleTimeString('es-CO');
    }, 1000);
});
