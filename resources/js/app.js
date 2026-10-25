import Chart from 'chart.js/auto';
import QRCode from 'qrcode';

/**
 * FiberPulse AI — Dashboard JS
 * SSE · Chart.js · QR · Dark/Light · PIN Admin · Anomaly Inject
 */

const CONFIG = window.FIBERPULSE_CONFIG || {};
const INITIAL = window.FIBERPULSE_DATA || {};

// ─── PIN de acceso al panel de administrador ───
// Cámbialo aquí o muévelo a una variable de entorno del lado servidor si necesitas más seguridad.
const ADMIN_PIN = '0117';
const PIN_SESSION_KEY = 'fiberpulse_admin_auth';

// ─── Estado global ───
const state = {
    telemetry:    INITIAL.latest   || null,
    analysis:     INITIAL.analysis || null,
    anomaly:      INITIAL.anomaly  || null,
    charts:       {},
    anomalyTimer: null,
};

// ─── Métricas ───
const METRICS = {
    cpu_usage:          { id: 'cpu',     unit: '%',    thresholds: { warning: 70,  critical: 85  }, isPercent: true  },
    ram_usage:          { id: 'ram',     unit: '%',    thresholds: { warning: 75,  critical: 85  }, isPercent: true  },
    temperature:        { id: 'temp',    unit: '°C',   thresholds: { warning: 70,  critical: 80  }, isPercent: true  },
    network_throughput: { id: 'net',     unit: 'Mbps', thresholds: { warning: 800, critical: 950 }, isPercent: false },
    latency_ms:         { id: 'latency', unit: 'ms',   thresholds: { warning: 100, critical: 200 }, isPercent: false },
    optical_signal_dbm: { id: 'optical', unit: 'dBm',  thresholds: { warning: -25, critical: -35 }, isPercent: false, inverted: true },
};

const CHART_COLORS = {
    cpu:  { dark: '#00ffd5', light: '#0891b2' },
    ram:  { dark: '#a855f7', light: '#7c3aed' },
    temp: { dark: '#ff3366', light: '#dc2626' },
    net:  { dark: '#00ff88', light: '#16a34a' },
};

// ══════════════════════════════════════════════════
//  TEMA
// ══════════════════════════════════════════════════

function isDark() { return document.documentElement.classList.contains('dark'); }

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    localStorage.setItem('fiberpulse-theme', theme);
    updateChartsTheme();
}

function getStoredTheme() {
    return localStorage.getItem('fiberpulse-theme') ||
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
}

// ══════════════════════════════════════════════════
//  GRÁFICOS
// ══════════════════════════════════════════════════

function getChartColor(key) {
    return (CHART_COLORS[key] || CHART_COLORS.cpu)[isDark() ? 'dark' : 'light'];
}
function getGridColor() { return isDark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'; }
function getTickColor() { return isDark() ? 'rgba(255,255,255,0.4)'  : 'rgba(0,0,0,0.4)';  }

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${alpha})`;
}

function createChart(canvasId, colorKey, yMax) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const color = getChartColor(colorKey);
    return new Chart(canvas, {
        type: 'line',
        data: { labels: [], datasets: [{ data: [], borderColor: color, backgroundColor: hexToRgba(color, 0.12), fill: true, tension: 0.4, pointRadius: 0, borderWidth: 2 }] },
        options: {
            responsive: true, maintainAspectRatio: false, animation: { duration: 0 },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: isDark() ? 'rgba(15,23,42,0.95)' : 'rgba(255,255,255,0.95)', titleColor: isDark() ? '#e2e8f0' : '#1e293b', bodyColor: isDark() ? '#94a3b8' : '#64748b', borderColor: isDark() ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)', borderWidth: 1, padding: 10, displayColors: false },
            },
            scales: {
                x: { display: false, grid: { color: getGridColor() } },
                y: { min: 0, max: yMax || undefined, grid: { color: getGridColor() }, ticks: { color: getTickColor(), font: { size: 9 }, maxTicksLimit: 4 } },
            },
        },
    });
}

function initCharts() {
    state.charts.cpu  = createChart('chart-cpu',  'cpu',  100);
    state.charts.ram  = createChart('chart-ram',  'ram',  100);
    state.charts.temp = createChart('chart-temp', 'temp', 100);
    state.charts.net  = createChart('chart-net',  'net',  null);
    if (INITIAL.history?.length) {
        INITIAL.history.forEach(p => pushChartPoint(p));
        Object.values(state.charts).forEach(c => c?.update('none'));
    }
}

function pushChartPoint(data) {
    const time = (data.recorded_at || new Date().toISOString()).slice(11, 19);
    [['cpu', data.cpu_usage], ['ram', data.ram_usage], ['temp', data.temperature], ['net', data.network_throughput]].forEach(([key, val]) => {
        const c = state.charts[key];
        if (!c) return;
        c.data.labels.push(time);
        c.data.datasets[0].data.push(val);
        if (c.data.labels.length > 30) { c.data.labels.shift(); c.data.datasets[0].data.shift(); }
    });
}

function updateChartsTheme() {
    Object.entries(state.charts).forEach(([key, chart]) => {
        if (!chart) return;
        const color = getChartColor(key);
        chart.data.datasets[0].borderColor = color;
        chart.data.datasets[0].backgroundColor = hexToRgba(color, 0.12);
        chart.options.scales.x.grid.color = getGridColor();
        chart.options.scales.y.grid.color = getGridColor();
        chart.options.scales.y.ticks.color = getTickColor();
        chart.options.plugins.tooltip.backgroundColor = isDark() ? 'rgba(15,23,42,0.95)' : 'rgba(255,255,255,0.95)';
        chart.options.plugins.tooltip.titleColor = isDark() ? '#e2e8f0' : '#1e293b';
        chart.options.plugins.tooltip.bodyColor  = isDark() ? '#94a3b8' : '#64748b';
        chart.update('none');
    });
}

// ══════════════════════════════════════════════════
//  MÉTRICAS
// ══════════════════════════════════════════════════

function getMetricStatus(key, value) {
    const m = METRICS[key];
    if (!m) return 'normal';
    if (m.inverted) {
        if (value <= m.thresholds.critical) return 'critical';
        if (value <= m.thresholds.warning)  return 'warning';
    } else {
        if (value >= m.thresholds.critical) return 'critical';
        if (value >= m.thresholds.warning)  return 'warning';
    }
    return 'normal';
}

function getValueColorClass(status) {
    if (isDark()) {
        if (status === 'critical') return 'text-[#ff3366] neon-red';
        if (status === 'warning')  return 'text-[#ffd000] neon-yellow';
        return 'text-[#00ff88] neon-green';
    }
    if (status === 'critical') return 'text-red-600';
    if (status === 'warning')  return 'text-yellow-600';
    return 'text-green-600';
}

function getBarColorClass(status) {
    if (isDark()) {
        if (status === 'critical') return 'bg-[#ff3366] shadow-[0_0_8px_rgba(255,51,102,0.5)]';
        if (status === 'warning')  return 'bg-[#ffd000] shadow-[0_0_8px_rgba(255,208,0,0.5)]';
        return 'bg-[#00ff88] shadow-[0_0_8px_rgba(0,255,136,0.4)]';
    }
    if (status === 'critical') return 'bg-red-500';
    if (status === 'warning')  return 'bg-yellow-500';
    return 'bg-green-500';
}

function updateMetrics(data) {
    Object.entries(METRICS).forEach(([key, cfg]) => {
        const value = data[key];
        if (value === undefined || value === null) return;
        const status = getMetricStatus(key, value);
        const valEl = document.getElementById(`metric-${cfg.id}-value`);
        const ledEl = document.getElementById(`metric-${cfg.id}-led`);
        const barEl = document.getElementById(`metric-${cfg.id}-bar`);
        if (valEl) {
            valEl.textContent = Number.isInteger(value) ? value : value.toFixed(1);
            valEl.className = `text-4xl font-bold tabular-nums ${getValueColorClass(status)}`;
        }
        if (ledEl) ledEl.className = `led led-${status}${status !== 'normal' ? ' led-pulse' : ''}`;
        if (barEl && cfg.isPercent) {
            barEl.style.width = `${Math.min(value, 100)}%`;
            barEl.className = `h-full rounded-full transition-all duration-500 ${getBarColorClass(status)}`;
        }
    });
    updateSystemStatus(data.status);
}

function updateSystemStatus(status) {
    const led  = document.getElementById('system-led');
    const text = document.getElementById('system-status-text');
    if (!led || !text) return;
    led.className  = `led led-${status}${status !== 'normal' ? ' led-pulse' : ''}`;
    text.textContent = status.toUpperCase();
    text.className = `text-xs font-bold uppercase tracking-wider ${getValueColorClass(status)}`;
}

// ══════════════════════════════════════════════════
//  BANNER IA
// ══════════════════════════════════════════════════

function updateAIBanner(data) {
    state.analysis = data;
    const banner = document.getElementById('ai-banner');
    if (!banner || !data) return;
    const status = (data.status || 'OPTIMAL').toUpperCase();
    const sc = statusToClass(status);
    banner.className = `glass-card rounded-2xl p-5 border ${sc.border} animate-banner-flash`;
    const statusEl = document.getElementById('ai-status');
    if (statusEl) { statusEl.textContent = status; statusEl.className = `status-badge ${sc.badge}`; }
    const predEl = document.getElementById('ai-predicted');
    if (predEl) predEl.textContent = data.predicted_failure || 'Sin diagnóstico.';
    const actionEl = document.getElementById('ai-action');
    if (actionEl) actionEl.textContent = data.recommended_action || 'Continuar monitoreo.';
    const sourceEl = document.getElementById('ai-source');
    if (sourceEl) {
        const src = data.source || 'unknown';
        sourceEl.textContent = src === 'gemini' ? CONFIG.geminiModel : 'Análisis Local (umbrales)';
        sourceEl.className = `text-[11px] font-medium ${src === 'gemini' ? 'text-cyan-600 dark:text-[#00ffd5]' : 'text-slate-500 dark:text-slate-400'}`;
    }
    const timeEl = document.getElementById('ai-timestamp');
    if (timeEl) timeEl.textContent = formatTime(data.timestamp);
    banner.style.boxShadow = status === 'CRITICAL'
        ? (isDark() ? '0 0 24px rgba(255,51,102,0.3),0 4px 24px rgba(0,0,0,0.4)' : '0 0 24px rgba(220,38,38,0.2),0 4px 24px rgba(0,0,0,0.06)')
        : status === 'WARNING'
            ? (isDark() ? '0 0 24px rgba(255,208,0,0.2),0 4px 24px rgba(0,0,0,0.4)' : '0 0 24px rgba(202,138,4,0.15),0 4px 24px rgba(0,0,0,0.06)')
            : '';
}

function statusToClass(status) {
    const map = {
        OPTIMAL:  { border: isDark() ? 'border-[#00ff88]/30' : 'border-green-200',  badge: isDark() ? 'bg-[#00ff88]/15 text-[#00ff88]' : 'bg-green-100 text-green-700' },
        WARNING:  { border: isDark() ? 'border-[#ffd000]/30' : 'border-yellow-200', badge: isDark() ? 'bg-[#ffd000]/15 text-[#ffd000]' : 'bg-yellow-100 text-yellow-700' },
        CRITICAL: { border: isDark() ? 'border-[#ff3366]/30' : 'border-red-200',    badge: isDark() ? 'bg-[#ff3366]/15 text-[#ff3366]' : 'bg-red-100 text-red-700' },
    };
    return map[status] || map.OPTIMAL;
}

// ══════════════════════════════════════════════════
//  BANNER ANOMALÍA
// ══════════════════════════════════════════════════

function updateAnomaly(data) {
    state.anomaly = data;
    const banner = document.getElementById('anomaly-banner');
    if (!banner) return;
    if (!data) {
        banner.classList.add('hidden');
        if (state.anomalyTimer) { clearInterval(state.anomalyTimer); state.anomalyTimer = null; }
        return;
    }
    banner.classList.remove('hidden');
    const labelEl = document.getElementById('anomaly-label');
    if (labelEl) labelEl.textContent = data.type || 'Anomalía activa';
    updateAnomalyCountdown(data);
    if (state.anomalyTimer) clearInterval(state.anomalyTimer);
    state.anomalyTimer = setInterval(() => updateAnomalyCountdown(data), 1000);
}

function updateAnomalyCountdown(data) {
    const remaining = Math.max(0, (data.duration || 0) - Math.floor(Date.now() / 1000 - (data.started_at || Date.now() / 1000)));
    const el = document.getElementById('anomaly-countdown');
    if (el) el.textContent = `${remaining}s restantes`;
    if (remaining <= 0) { clearInterval(state.anomalyTimer); state.anomalyTimer = null; }
}

// ══════════════════════════════════════════════════
//  QR CODE
// ══════════════════════════════════════════════════

function generateQRCode() {
    const canvas  = document.getElementById('qr-canvas');
    const wrapper = document.getElementById('qr-wrapper');
    if (!canvas || !CONFIG.localUrl) return;
    if (wrapper) wrapper.style.visibility = 'hidden';
    QRCode.toCanvas(canvas, CONFIG.localUrl, {
        width: 240, margin: 2, errorCorrectionLevel: 'M',
        color: { dark: '#0a0e17', light: '#ffffff' },
    }, (err) => {
        if (err) { console.error('QR error:', err); return; }
        if (wrapper) wrapper.style.visibility = 'visible';
    });
}

// ══════════════════════════════════════════════════
//  SSE
// ══════════════════════════════════════════════════

function initSSE() {
    setSSEStatus('Conectando...', 'yellow');
    const src = new EventSource('/telemetry/stream');
    src.addEventListener('open', () => setSSEStatus('Conectado', 'green'));
    src.addEventListener('telemetry', (e) => {
        try {
            const data = JSON.parse(e.data);
            state.telemetry = data;
            updateMetrics(data);
            pushChartPoint(data);
            Object.values(state.charts).forEach(c => c?.update('none'));
        } catch (err) { console.error('SSE telemetry:', err); }
    });
    src.addEventListener('analysis', (e) => {
        try { updateAIBanner(JSON.parse(e.data)); } catch {}
    });
    src.addEventListener('anomaly', (e) => {
        try { updateAnomaly(e.data === 'null' ? null : JSON.parse(e.data)); } catch { updateAnomaly(null); }
    });
    src.addEventListener('connections', (e) => {
        try {
            const { count } = JSON.parse(e.data);
            document.querySelectorAll('#user-count, #user-count-side').forEach(el => { el.textContent = count; });
        } catch {}
    });
    src.onerror = () => setSSEStatus('Reconectando...', 'yellow');
}

function setSSEStatus(text, color) {
    const cls = color === 'green'
        ? 'text-xs font-medium text-green-600 dark:text-[#00ffd5]'
        : 'text-xs font-medium text-yellow-600 dark:text-[#ffd000]';
    document.querySelectorAll('#sse-status, #sse-status-side').forEach(el => { el.textContent = text; el.className = cls; });
}

// ══════════════════════════════════════════════════
//  FETCH helper
// ══════════════════════════════════════════════════

async function postJSON(url, body = {}) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        return await res.json();
    } catch (err) {
        console.error('POST error:', err);
        return { success: false, message: 'Error de conexión con el servidor.' };
    }
}

// ══════════════════════════════════════════════════
//  TOAST
// ══════════════════════════════════════════════════

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const styles = {
        success: isDark() ? 'border-[#00ff88]/30 text-[#00ff88]' : 'border-green-200 text-green-700',
        warning: isDark() ? 'border-[#ffd000]/30 text-[#ffd000]' : 'border-yellow-200 text-yellow-700',
        error:   isDark() ? 'border-[#ff3366]/30 text-[#ff3366]' : 'border-red-200 text-red-700',
        info:    isDark() ? 'border-white/10 text-slate-200'      : 'border-slate-200 text-slate-700',
    };
    const toast = document.createElement('div');
    toast.className = `toast-enter glass-card px-4 py-3 rounded-xl border ${styles[type] || styles.info} text-sm font-medium mb-2`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.cssText += 'opacity:0;transform:translateY(-8px);transition:all 0.3s ease;';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ══════════════════════════════════════════════════
//  MODALES (helper genérico)
// ══════════════════════════════════════════════════

function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('hidden');
    requestAnimationFrame(() => m.classList.add('modal-open'));
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('modal-open');
    setTimeout(() => m.classList.add('hidden'), 250);
}

// ══════════════════════════════════════════════════
//  PIN: Autenticación de administrador
// ══════════════════════════════════════════════════

function isAdminAuthenticated() {
    return sessionStorage.getItem(PIN_SESSION_KEY) === 'true';
}

function setAdminAuthenticated() {
    sessionStorage.setItem(PIN_SESSION_KEY, 'true');
}

function handleOpenAdmin() {
    if (isAdminAuthenticated()) {
        // Ya autenticado en esta sesión → abrir panel directo
        openModal('modal-instructor');
    } else {
        // Pedir PIN
        openModal('modal-pin');
        setTimeout(() => document.getElementById('pin-input')?.focus(), 300);
    }
}

function verifyPin() {
    const input = document.getElementById('pin-input');
    const error = document.getElementById('pin-error');
    if (!input) return;

    const entered = input.value.trim();

    if (entered === ADMIN_PIN) {
        // PIN correcto
        setAdminAuthenticated();
        error?.classList.add('hidden');
        input.value = '';
        closeModal('modal-pin');
        // Pequeño delay para que el modal PIN se cierre antes de abrir el panel
        setTimeout(() => openModal('modal-instructor'), 280);
    } else {
        // PIN incorrecto
        input.value = '';
        error?.classList.remove('hidden');
        input.classList.add('border-red-500', 'dark:border-[#ff3366]');
        setTimeout(() => {
            error?.classList.add('hidden');
            input.classList.remove('border-red-500', 'dark:border-[#ff3366]');
        }, 2500);
        input.focus();
    }
}

// ══════════════════════════════════════════════════
//  BOTONES: Anomalías AJAX
// ══════════════════════════════════════════════════

async function injectAnomaly(type, btn) {
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Inyectando...`;

    const result = await postJSON(`/telemetry/inject/${type}`, { duration: 60 });

    if (result.success) {
        showToast(`⚡ ${result.message}`, 'warning');
    } else {
        showToast(`Error: ${result.message}`, 'error');
    }

    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }, 2000);
}

async function clearAnomaly() {
    const result = await postJSON('/telemetry/clear');
    showToast(result.success ? '✅ ' + result.message : '❌ ' + result.message, result.success ? 'success' : 'error');
}

// ══════════════════════════════════════════════════
//  INIT BOTONES
// ══════════════════════════════════════════════════

function initButtons() {
    // ── Tema ──
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        applyTheme(isDark() ? 'light' : 'dark');
    });

    // ── QR Modal ──
    document.getElementById('btn-open-qr')?.addEventListener('click', () => {
        openModal('modal-qr');
        setTimeout(() => generateQRCode(), 50);
    });
    document.getElementById('btn-close-qr')?.addEventListener('click', () => closeModal('modal-qr'));
    document.getElementById('modal-qr-backdrop')?.addEventListener('click', () => closeModal('modal-qr'));

    document.getElementById('copy-url-btn')?.addEventListener('click', () => {
        navigator.clipboard.writeText(CONFIG.localUrl || '')
            .then(() => showToast('URL copiada al portapapeles', 'success'))
            .catch(() => showToast('No se pudo copiar', 'error'));
    });

    // ── Botón Administrador → abre PIN o panel según sesión ──
    document.getElementById('btn-open-pin')?.addEventListener('click', handleOpenAdmin);

    // ── Modal PIN ──
    document.getElementById('btn-close-pin')?.addEventListener('click', () => {
        document.getElementById('pin-input').value = '';
        document.getElementById('pin-error')?.classList.add('hidden');
        closeModal('modal-pin');
    });
    document.getElementById('modal-pin-backdrop')?.addEventListener('click', () => {
        document.getElementById('pin-input').value = '';
        document.getElementById('pin-error')?.classList.add('hidden');
        closeModal('modal-pin');
    });
    document.getElementById('btn-confirm-pin')?.addEventListener('click', verifyPin);
    document.getElementById('pin-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') verifyPin();
    });

    // ── Panel Administrador ──
    document.getElementById('btn-close-instructor')?.addEventListener('click', () => closeModal('modal-instructor'));
    document.getElementById('modal-instructor-backdrop')?.addEventListener('click', () => closeModal('modal-instructor'));

    // ── Botones de anomalía (AJAX) ──
    document.querySelectorAll('[data-inject]').forEach(btn => {
        btn.addEventListener('click', () => injectAnomaly(btn.dataset.inject, btn));
    });

    // ── Detener anomalía (banner inline + modal) ──
    const stopHandler = () => clearAnomaly();
    document.getElementById('clear-anomaly-btn')?.addEventListener('click', stopHandler);
    document.getElementById('clear-anomaly-btn-modal')?.addEventListener('click', () => {
        clearAnomaly();
        closeModal('modal-instructor');
    });

    // ── Escape cierra cualquier modal ──
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        ['modal-qr', 'modal-pin', 'modal-instructor'].forEach(id => closeModal(id));
    });
}

// ══════════════════════════════════════════════════
//  UTILS
// ══════════════════════════════════════════════════

function formatTime(ts) {
    if (!ts) return '';
    try { return new Date(ts).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); }
    catch { return ''; }
}

// ══════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(getStoredTheme());
    initCharts();

    // URL local en texto del modal QR
    document.querySelectorAll('.local-url-text').forEach(el => {
        if (CONFIG.localUrl) el.textContent = CONFIG.localUrl;
    });

    // Badge Gemini
    const geminiBadge = document.getElementById('gemini-badge');
    if (geminiBadge) {
        geminiBadge.textContent = CONFIG.geminiEnabled ? (CONFIG.geminiModel || 'Gemini') : 'Análisis Local';
        geminiBadge.className = 'status-badge ' + (CONFIG.geminiEnabled
            ? (isDark() ? 'bg-[#00ffd5]/15 text-[#00ffd5]' : 'bg-cyan-100 text-cyan-700')
            : (isDark() ? 'bg-slate-700 text-slate-300'     : 'bg-slate-200 text-slate-600'));
    }

    // Datos iniciales desde server
    if (state.telemetry) updateMetrics(state.telemetry);
    if (state.analysis)  updateAIBanner(state.analysis);
    if (state.anomaly)   updateAnomaly(state.anomaly);

    initSSE();
    initButtons();

    // Reloj
    setInterval(() => {
        const el = document.getElementById('dashboard-clock');
        if (el) el.textContent = new Date().toLocaleTimeString('es-CO');
    }, 1000);
});
