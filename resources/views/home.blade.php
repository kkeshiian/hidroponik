@extends('layouts.app')

@section('content')
<div class="w-full py-8 px-6 grid grid-cols-1 md:grid-cols-2 gap-8">

    <!-- Kebun A Card -->
    <div class="bg-white shadow-md rounded-lg p-6 w-full max-w-full">
        <h2 class="text-2xl font-bold text-[var(--color-text-main)]">Kebun A</h2>
        <div class="text-gray-500 mb-2">Realtime Monitoring</div>
        <div class="mb-4 flex items-center gap-2">
            <div class="text-xs font-semibold text-slate-600">Mode:</div>
            <div id="kebun-a-mode" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">UNKNOWN</div>
        </div>
        <div class="flex flex-row items-center justify-center gap-6 md:gap-12 lg:gap-24 mb-4 overflow-auto">
            <div class="text-center px-1">
                <div class="font-semibold text-[var(--color-primary)] text-sm sm:text-base whitespace-nowrap">pH Level</div>
                <div id="kebun-a-ph" class="text-xl sm:text-3xl md:text-2xl font-bold text-[var(--color-primary)]">--</div>
            </div>
            <div class="text-center px-1">
                <div class="font-semibold text-purple-600 text-sm sm:text-base whitespace-nowrap">TDS Level</div>
                <div id="kebun-a-tds" class="text-xl sm:text-3xl md:text-2xl font-bold text-purple-600">--</div>
            </div>
            <div class="text-center px-1">
                <div class="font-semibold text-blue-600 text-sm sm:text-base whitespace-nowrap">Suhu Air</div>
                <div id="kebun-a-suhu" class="text-xl sm:text-3xl md:text-2xl font-bold text-blue-600">--</div>
            </div>
        </div>
        <div class="h-64">
            <canvas id="chartA" class="w-full h-full"></canvas>
        </div>
    </div>
    <!-- Kebun B Card -->
    <div class="bg-white shadow-md rounded-lg p-6 w-full md:w-[920px] max-w-full">
        <h2 class="text-2xl font-bold text-[var(--color-text-main)]">Kebun B</h2>
        <div class="text-gray-500 mb-2">Realtime Monitoring</div>
        <div class="mb-4 flex items-center gap-2">
            <div class="text-xs font-semibold text-slate-600">Mode:</div>
            <div id="kebun-b-mode" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">UNKNOWN</div>
        </div>
        <div class="flex flex-row items-center justify-center gap-6 md:gap-16 lg:gap-24 mb-4 overflow-auto">
            <div class="text-center px-1">
                <div class="font-semibold text-[var(--color-primary)] text-sm sm:text-base whitespace-nowrap">pH Level</div>
                <div id="kebun-b-ph" class="text-3xl sm:text-3xl md:text-2xl font-bold text-[var(--color-primary)]">--</div>
            </div>
            <div class="text-center px-1">
                <div class="font-semibold text-purple-600 text-sm sm:text-base whitespace-nowrap">TDS Level</div>
                <div id="kebun-b-tds" class="text-3xl sm:text-3xl md:text-2xl font-bold text-purple-600">--</div>
            </div>
            <div class="text-center px-1">
                <div class="font-semibold text-blue-600 text-sm sm:text-base whitespace-nowrap">Suhu Air</div>
                <div id="kebun-b-suhu" class="text-3xl sm:text-3xl md:text-2xl font-bold text-blue-600">--</div>
            </div>
        </div>
        <div class="h-64">
            <canvas id="chartB" class="w-full h-full"></canvas>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// --- Chart setup (Chart.js) ----------------------------------------------
const maxPoints = 15;

function createChart(ctx) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'pH', data: [], borderColor: '#16a34a', backgroundColor: 'transparent', tension: 0.2, yAxisID: 'y-ph' },
                { label: 'TDS', data: [], borderColor: '#a78bfa', backgroundColor: 'transparent', tension: 0.2, yAxisID: 'y-tds' },
                { label: 'Suhu', data: [], borderColor: '#3b82f6', backgroundColor: 'transparent', tension: 0.2, yAxisID: 'y-suhu' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            stacked: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const v = context.parsed && context.parsed.y;
                            if (label === 'TDS') {
                                return label + ': ' + (v == null ? '' : Math.trunc(v));
                            }
                            return label + ': ' + (v == null ? '' : Number(v).toFixed(1));
                        }
                    }
                }
            },
            scales: {
                x: { display: true, title: { display: false } },
                'y-ph': {
                    type: 'linear',
                    position: 'left',
                    min: 0,
                    max: 14,
                    title: { display: true, text: 'pH' }
                },
                'y-tds': {
                    type: 'linear',
                    position: 'right',
                    min: 0,
                    max: 1200,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'TDS (ppm)' }
                },
                'y-suhu': {
                    type: 'linear',
                    position: 'right',
                    min: -10,
                    max: 60,
                    // hide the visual axis (ticks/labels/title) but keep the scale for plotting
                    display: false,
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}

const chartA = createChart(document.getElementById('chartA').getContext('2d'));
const chartB = createChart(document.getElementById('chartB').getContext('2d'));
const lastRealtimeUpdate = {
    'kebun-a': 0,
    'kebun-b': 0,
};
const REALTIME_HOLD_MS = 15000;

function addPointToChart(chart, label, ph, tds, suhu) {
    // Prevent duplicate if label matches last label
    if (chart.data.labels.length > 0) {
        const lastLabel = chart.data.labels[chart.data.labels.length - 1];
        if (lastLabel === label) return;
    }

    chart.data.labels.push(label);
    chart.data.datasets[0].data.push(toNumber(ph));
    chart.data.datasets[1].data.push(toNumber(tds));
    chart.data.datasets[2].data.push(toNumber(suhu));
    // trim
    if (chart.data.labels.length > maxPoints) {
        chart.data.labels.shift();
        chart.data.datasets.forEach(ds => ds.data.shift());
    }
    chart.update('none');
}

// Utility: parse a value into a number or return null
function toNumber(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
}

// Utility: format a numeric value to `decimals` places, or return '--' for null/undefined
function formatNumber(v, decimals = 1) {
    const n = toNumber(v);
    return n === null ? '--' : n.toFixed(decimals);
}

function formatTds(v) {
    const n = toNumber(v);
    return n === null ? '--' : String(Math.trunc(n));
}

function parseModeFromAny(payloadOrStatus) {
    if (payloadOrStatus && typeof payloadOrStatus === 'object') {
        const rawMode = payloadOrStatus.mode || payloadOrStatus.device_mode || payloadOrStatus.current_mode;
        if (typeof rawMode === 'string') {
            const modeLc = rawMode.trim().toLowerCase();
            if (modeLc === 'mode_cal' || modeLc === 'cal' || modeLc === 'calibration') return 'mode_cal';
            if (modeLc === 'mode_auto' || modeLc === 'auto') return 'mode_auto';
        }
    }

    const text = String(payloadOrStatus || '').trim().toLowerCase();
    if (!text) return null;
    if (text.includes('mode_cal') || text.includes('mode cal') || text.includes('mode_changed:mode_cal') || text.includes('mode_set:mode_cal')) return 'mode_cal';
    if (text.includes('mode_auto') || text.includes('mode auto') || text.includes('mode_changed:mode_auto') || text.includes('mode_set:mode_auto')) return 'mode_auto';
    return null;
}

function setHomeMode(kebun, mode) {
    const normalized = normalizeKebun(kebun);
    if (!normalized) return;
    const el = document.getElementById(`${normalized}-mode`);
    if (!el) return;

    if (mode === 'mode_cal') {
        el.textContent = 'MODE_CAL';
        el.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700';
        return;
    }

    if (mode === 'mode_auto') {
        el.textContent = 'MODE_AUTO';
        el.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700';
        return;
    }

    el.textContent = 'UNKNOWN';
    el.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700';
}

function normalizeKebun(kebun) {
    if (!kebun) return null;
    const key = String(kebun).toLowerCase();
    if (key === 'kebun-1' || key === 'a') return 'kebun-a';
    if (key === 'kebun-2' || key === 'b') return 'kebun-b';
    return key;
}

function getSuhu(payload) {
    if (!payload) return null;
    return payload.suhu != null ? payload.suhu : payload.suhu_air;
}

function kebunToChart(kebun) {
    const normalized = normalizeKebun(kebun);
    if (!normalized) return null;
    if (normalized === 'kebun-a') return chartA;
    if (normalized === 'kebun-b') return chartB;
    return null;
}

function updateChartFromPayload(kebun, payload) {
    const normalizedKebun = normalizeKebun(kebun);
    const chart = kebunToChart(normalizedKebun);
    if (!chart) return;
    const timestamp = new Date();
    // Prefer recorded_at from API/DB payload to avoid adding duplicate points each poll.
    if (payload.recorded_at) {
        const t = new Date(payload.recorded_at);
        if (!isNaN(t)) timestamp.setTime(t.getTime());
    } else if (payload.date && payload.time) {
        // if payload has date/time try to parse
        const t = new Date(payload.date + ' ' + payload.time);
        if (!isNaN(t)) timestamp.setTime(t.getTime());
    }
    const phVal = payload.ph != null ? Number(parseFloat(payload.ph).toFixed(1)) : null;
    const tdsVal = payload.tds != null ? toNumber(payload.tds) : null;
    const suhuRaw = getSuhu(payload);
    const suhuVal = suhuRaw != null ? Number(parseFloat(suhuRaw).toFixed(1)) : null;
    addPointToChart(chart, timestamp.toLocaleTimeString(), phVal, tdsVal, suhuVal);
}

function hasFreshRealtime(kebun) {
    const normalized = normalizeKebun(kebun);
    if (!normalized) return false;
    return (Date.now() - (lastRealtimeUpdate[normalized] || 0)) < REALTIME_HOLD_MS;
}

// --- Poll latest telemetry and update DOM + charts ------------------------
async function fetchHistory() {
    try {
        const res = await fetch('/api/telemetry/history');
        if (!res.ok) return;
        const data = await res.json();

        ['kebun-a', 'kebun-b'].forEach(kebun => {
            if (data[kebun] && Array.isArray(data[kebun])) {
                data[kebun].forEach(row => {
                    const t = new Date(row.recorded_at);
                    const label = t.toLocaleTimeString();
                    const chart = kebunToChart(kebun);
                    if (chart) {
                        addPointToChart(chart, label, row.ph, row.tds, getSuhu(row));
                    }
                });
            }
        });
    } catch (e) {
        console.error('Failed to fetch history', e);
    }
}

async function fetchTelemetry() {
    try {
        const res = await fetch('/api/telemetry/latest');
        if (!res.ok) return;
        const data = await res.json();

        if (data['kebun-a']) {
            const a = data['kebun-a'];
            const modeA = parseModeFromAny(a);
            if (modeA) setHomeMode('kebun-a', modeA);
            if (!hasFreshRealtime('kebun-a')) {
                document.getElementById('kebun-a-ph').innerText = a.ph != null ? formatNumber(a.ph, 1) : '--';
                document.getElementById('kebun-a-tds').innerText = formatTds(a.tds);
                const suhuA = getSuhu(a);
                document.getElementById('kebun-a-suhu').innerText = suhuA != null ? formatNumber(suhuA, 1) : '--';
                updateChartFromPayload('kebun-a', a);
            }
        }
        if (data['kebun-b']) {
            const b = data['kebun-b'];
            const modeB = parseModeFromAny(b);
            if (modeB) setHomeMode('kebun-b', modeB);
            if (!hasFreshRealtime('kebun-b')) {
                document.getElementById('kebun-b-ph').innerText = b.ph != null ? formatNumber(b.ph, 1) : '--';
                document.getElementById('kebun-b-tds').innerText = formatTds(b.tds);
                const suhuB = getSuhu(b);
                document.getElementById('kebun-b-suhu').innerText = suhuB != null ? formatNumber(suhuB, 1) : '--';
                updateChartFromPayload('kebun-b', b);
            }
        }
    } catch (e) {
        // ignore network errors
    }
}

// Initial load
fetchHistory().then(() => {
    // start polling every 2s after history is loaded
    setInterval(fetchTelemetry, 1000);
    fetchTelemetry();
});
</script>
<!-- MQTT over WebSocket (realtime) -->
<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script>
// Try to connect via WebSocket to EMQX for realtime updates
(() => {
    const wsUrl = 'wss://broker.emqx.io:8084/mqtt';
    const clientId = 'web-client-' + Math.random().toString(16).substr(2, 8);
    const opts = { keepalive: 30, clientId, reconnectPeriod: 2000 };

    
    try {
        const client = mqtt.connect(wsUrl, opts);

        client.on('connect', () => {
            console.info('MQTT.js connected to', wsUrl);
            // subscribe to publish topics
            client.subscribe('hidroganik/+/publish', { qos: 0 }, (err) => {
                if (err) console.warn('subscribe error', err);
            });
            client.subscribe('hidroganik/+/status', { qos: 0 }, (err) => {
                if (err) console.warn('subscribe error', err);
            });
        });

        client.on('reconnect', () => console.info('MQTT.js reconnecting...'));
        client.on('error', (err) => console.error('MQTT.js error', err));

        client.on('message', (topic, message) => {
            try {
                const parts = topic.split('/');
                const kebun = normalizeKebun(parts[1]);
                if (!kebun) return;
                const kind = parts[2] || '';

                if (kind === 'status') {
                    const raw = message.toString().trim();
                    let statusPayload = raw;
                    try {
                        statusPayload = JSON.parse(raw);
                    } catch (_) {
                        // string status masih valid
                    }

                    const modeFromStatus = parseModeFromAny(statusPayload);
                    if (modeFromStatus) {
                        setHomeMode(kebun, modeFromStatus);
                    }
                    return;
                }

                const payload = JSON.parse(message.toString());

                // map kebun to element ids (kebun-a -> kebun-a-ph etc)
                const phEl = document.getElementById(`${kebun}-ph`);
                const tdsEl = document.getElementById(`${kebun}-tds`);
                const suhuEl = document.getElementById(`${kebun}-suhu`);

                if (phEl) phEl.innerText = payload.ph != null ? formatNumber(payload.ph, 1) : phEl.innerText;
                if (tdsEl) tdsEl.innerText = payload.tds != null ? formatTds(payload.tds) : tdsEl.innerText;
                const suhuValue = getSuhu(payload);
                if (suhuEl) suhuEl.innerText = suhuValue != null ? formatNumber(suhuValue, 1) : suhuEl.innerText;
                const modeFromPayload = parseModeFromAny(payload);
                if (modeFromPayload) {
                    setHomeMode(kebun, modeFromPayload);
                }
                lastRealtimeUpdate[kebun] = Date.now();

                // update chart
                if (typeof updateChartFromPayload === 'function') {
                    updateChartFromPayload(kebun, payload);
                }
            } catch (e) {
                console.error('Invalid MQTT message', e);
            }
        });
    } catch (e) {
        console.warn('MQTT WebSocket not available', e);
    }
})();
</script>
@include('components.footer')

@endsection
