@extends('layouts.app')

@section('content')
@php
    $kebunCards = [
        ['key' => 'kebun-a', 'label' => 'Kebun A', 'device' => 'kebun-1'],
        ['key' => 'kebun-b', 'label' => 'Kebun B', 'device' => 'kebun-2'],
    ];
@endphp

<div class="w-full py-8 px-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        @foreach ($kebunCards as $card)
            <div class="bg-white shadow-md rounded-lg p-6 w-full max-w-full" data-kebun-card="{{ $card['key'] }}">
                <h2 class="text-2xl font-bold text-[var(--color-text-main)] mb-2">{{ $card['label'] }}</h2>
                <div class="text-gray-500 mb-6">Kalibrasi MQTT + Live Preview</div>

                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-3 sm:p-4 border border-green-200 shadow-sm">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="text-xs font-medium text-green-700 uppercase">TDS Level</div>
                        </div>
                        <div class="space-y-2">
                            <div class="bg-white/70 backdrop-blur rounded-lg p-2 sm:p-3 border border-green-200/50">
                                <div class="text-xs text-green-700 font-medium mb-1">Terkalibrasi</div>
                                <div id="{{ $card['key'] }}-tds-kalibrasi" class="text-xl sm:text-2xl font-bold text-green-700">--</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-3 sm:p-4 border border-green-200 shadow-sm">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="text-xs font-medium text-green-700 uppercase">Suhu Air</div>
                        </div>
                        <div class="space-y-2">
                            <div class="bg-white/70 backdrop-blur rounded-lg p-2 sm:p-3 border border-green-200/50">
                                <div class="text-xs text-green-700 font-medium mb-1">Terkalibrasi</div>
                                <div id="{{ $card['key'] }}-suhu-kalibrasi" class="text-xl sm:text-2xl font-bold text-green-700">--</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-100 border border-slate-200 rounded-lg p-3">
                        <div class="text-xs font-semibold text-slate-700 mb-1">Status ESP</div>
                        <div class="flex items-center gap-2">
                            <div id="esp-state-{{ $card['key'] }}" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">UNKNOWN</div>
                            <div id="esp-detail-{{ $card['key'] }}" class="text-xs text-slate-600 whitespace-pre-line">Menunggu status dari ESP...</div>
                        </div>
                        <div class="text-[11px] font-medium text-slate-600 mt-2">Status Command / MQTT Event</div>
                        <div id="status-{{ $card['key'] }}" class="text-sm text-slate-600">Menunggu koneksi MQTT...</div>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="text-xs font-semibold text-slate-700">Mode:</div>
                            <div id="mode-{{ $card['key'] }}" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">UNKNOWN</div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button type="button" data-kebun-mode="{{ $card['key'] }}" onclick="setModeCal('{{ $card['key'] }}')"
                                class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 rounded transition text-xs">
                                MODE_CAL
                            </button>
                            <button type="button" data-kebun-mode="{{ $card['key'] }}" onclick="setModeAuto('{{ $card['key'] }}')"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded transition text-xs">
                                MODE_AUTO
                            </button>
                        </div>
                        <div id="countdown-{{ $card['key'] }}" class="text-xs text-amber-700 mt-1 hidden"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="border border-green-200 rounded-lg p-3 bg-green-50">
                            <h4 class="font-semibold text-green-700 mb-2 text-xs">Kalibrasi pH</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="startPh401('{{ $card['key'] }}')"
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded transition shadow-sm text-xs">
                                    pH 4.01
                                </button>
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="startPh686('{{ $card['key'] }}')"
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded transition shadow-sm text-xs">
                                    pH 6.86
                                </button>
                            </div>
                            <div class="mt-2 rounded-md border border-green-200 bg-white/60 px-2 py-1.5 text-[11px] text-gray-700">
                                pH 4.01 = <span id="{{ $card['key'] }}-ph401-volt" class="font-semibold text-green-700">--</span> V <br>
                                pH 6.86 = <span id="{{ $card['key'] }}-ph686-volt" class="font-semibold text-green-700">--</span> V
                            </div>
                            <div class="text-[11px] text-gray-600 mt-2">Setiap kalibrasi pH menunggu stabilisasi 15 detik di ESP32.</div>
                        </div>

                        <div class="border border-green-200 rounded-lg p-3 bg-green-50">
                            <h4 class="font-semibold text-green-700 mb-2 text-xs">Kalibrasi TDS (ppm)</h4>
                            <div class="flex gap-2">
                                <input type="number" min="1" step="1" id="tds-ppm-{{ $card['key'] }}"
                                    class="w-full border border-gray-300 rounded px-2 py-2 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="Contoh: 500">
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="submitTds('{{ $card['key'] }}')"
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold px-3 rounded transition shadow-sm text-xs">
                                    Kirim
                                </button>
                            </div>
                            <div class="text-[11px] text-gray-600 mt-2">Command: <span class="font-mono">TDS:&lt;angka&gt;</span></div>
                        </div>

                        <div class="border border-green-200 rounded-lg p-3 bg-green-50">
                            <h4 class="font-semibold text-green-700 mb-2 text-xs">Offset Suhu</h4>
                            <div class="mb-2 rounded-md border border-green-200 bg-white/60 px-2 py-1.5 text-[11px] text-gray-700">
                                Suhu = <span id="{{ $card['key'] }}-offset-suhu" class="font-semibold text-green-700">--</span> °C <br>
                                Suhu Mentah = <span id="{{ $card['key'] }}-offset-suhu-raw" class="font-semibold text-green-700">--</span> °C
                            </div>
                            <div class="flex gap-2">
                                <input type="number" step="0.1" id="temp-offset-{{ $card['key'] }}"
                                    class="w-full border border-gray-300 rounded px-2 py-2 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    placeholder="Contoh: 1.5 atau -1">
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="submitTempOffset('{{ $card['key'] }}')"
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold px-3 rounded transition shadow-sm text-xs">
                                    Kirim
                                </button>
                            </div>
                            <div class="text-[11px] text-gray-600 mt-2">Command: <span class="font-mono">TEMP_OFFSET:&lt;angka&gt;</span></div>
                        </div>

                        <div class="border border-green-200 rounded-lg p-3 bg-green-50">
                            <h4 class="font-semibold text-green-700 mb-2 text-xs">Command Tambahan</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="sendQuickCommand('{{ $card['key'] }}', 'SAVE_CAL')"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded transition text-xs">
                                    SAVE
                                </button>
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="sendQuickCommand('{{ $card['key'] }}', 'LOAD_CAL')"
                                    class="bg-sky-600 hover:bg-sky-700 text-white font-semibold py-2 rounded transition text-xs">
                                    LOAD
                                </button>
                                <button type="button" data-kebun-action="{{ $card['key'] }}" onclick="sendQuickCommand('{{ $card['key'] }}', 'RESET_CAL')"
                                    class="bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2 rounded transition text-xs">
                                    RESET
                                </button>
                            </div>
                            <div class="text-[11px] text-gray-600 mt-2">Command dikirim sebagai string ke topic command.</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script>
const mqttBroker = 'wss://broker.emqx.io:8084/mqtt';
const kebunConfig = {
    'kebun-a': { device: 'kebun-1', label: 'Kebun A' },
    'kebun-b': { device: 'kebun-2', label: 'Kebun B' },
};

const deviceToUi = {
    'kebun-1': 'kebun-a',
    'kebun-2': 'kebun-b',
    'kebun-a': 'kebun-a',
    'kebun-b': 'kebun-b',
};

const statusOkMap = {
    PH401_START: ['ph401_saved'],
    PH686_START: ['ph686_saved'],
    TDS: ['tds_calibration_done'],
    TEMP_OFFSET: ['temp_offset_updated'],
    MODE_CAL: ['mode_cal_active', 'mode_changed:mode_cal', 'mode_set:mode_cal', 'mode_cal'],
    MODE_AUTO: ['mode_auto_active', 'mode_changed:mode_auto', 'mode_set:mode_auto', 'mode_auto'],
};

const statusFailMap = {
    TDS: ['tds_calibration_failed_invalid_input', 'tds_calibration_failed_raw_zero'],
};

const state = {
    'kebun-a': {
        busy: false,
        waiting: null,
        timeoutId: null,
        countdownId: null,
        mode: 'unknown',
        espMainState: 'unknown',
        power: {
            deviceState: 'boot',
            sleepSeconds: null,
            wakeEstimate: null,
            lastSensorAt: 0,
            mqttBurstUntil: 0,
            prevCurrent: 95,
            currentHistory: [],
            powerHistory: [],
            lastPayload: null,
        },
    },
    'kebun-b': {
        busy: false,
        waiting: null,
        timeoutId: null,
        countdownId: null,
        mode: 'unknown',
        espMainState: 'unknown',
        power: {
            deviceState: 'boot',
            sleepSeconds: null,
            wakeEstimate: null,
            lastSensorAt: 0,
            mqttBurstUntil: 0,
            prevCurrent: 95,
            currentHistory: [],
            powerHistory: [],
            lastPayload: null,
        },
    },
};

let mqttClient = null;
let dummyPowerTimer = null;
const deepSleepModeHint = 'Mode deep sleep: 60 detik atau 600 detik.';

function resolveDeepSleepModeLabel(seconds) {
    if (!Number.isFinite(seconds) || seconds <= 0) return 'mode belum terbaca';
    if (seconds >= 540) return 'mode 600 detik';
    if (seconds >= 45 && seconds <= 120) return 'mode 60 detik';
    return `mode ${Math.round(seconds)} detik`;
}

function buildDeepSleepDetail(sleepSeconds, wakeTimeText = '') {
    const modeLabel = resolveDeepSleepModeLabel(sleepSeconds);
    const modeLine = `Masuk ${modeLabel}. ${deepSleepModeHint}`;

    let wakeLine = 'Perkiraan bangun: menunggu data...';
    if (wakeTimeText) {
        wakeLine = `Perkiraan bangun: ${wakeTimeText}`;
    } else if (Number.isFinite(sleepSeconds) && sleepSeconds > 0) {
        const eta = formatWakeEstimate(sleepSeconds);
        if (eta) {
            wakeLine = `Perkiraan bangun: ${eta}`;
        }
    }

    return `${modeLine}\n${wakeLine}`;
}

function persistUiState() {
    // Intentionally no-op: runtime source of truth is backend cache/API for cross-device consistency.
}

function restoreUiState() {
    // Intentionally no-op: runtime source of truth is backend cache/API for cross-device consistency.
}

function applyRuntimeStateFromBackend(kebun, runtime) {
    if (!runtime || !state[kebun]) return;

    const modeRaw = String(runtime.mode || '').toUpperCase();
    if (modeRaw === 'CALIBRATION') {
        setMode(kebun, 'mode_cal');
    } else if (modeRaw === 'AUTO') {
        setMode(kebun, 'mode_auto');
    }

    const badge = espStateEl(kebun);
    const detail = espDetailEl(kebun);
    if (!badge || !detail) return;

    const runtimeState = String(runtime.state || '').toUpperCase();

    let label = 'UNKNOWN';
    let detailText = 'Menunggu status dari ESP...';
    let badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700';

    if (runtimeState === 'BOOT') {
        label = 'Menyala / Restart';
        detailText = 'Status terakhir: boot';
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700';
        state[kebun].espMainState = 'boot';
    } else if (runtimeState === 'ACTIVE') {
        label = 'Active';
        detailText = 'Status terakhir: active';
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700';
        state[kebun].espMainState = 'awake';
    } else if (runtimeState === 'CALIBRATION') {
        label = 'Calibration';
        detailText = 'Status terakhir: calibration';
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700';
        state[kebun].espMainState = 'awake';
    } else if (runtimeState === 'SLEEPING') {
        const sleepUntil = Number(runtime.sleep_until ?? 0);
        let sleepSeconds = Number(runtime.sleep_seconds ?? 0);
        let wakeTimeText = '';

        if (Number.isFinite(sleepUntil) && sleepUntil > 0) {
            const remaining = Math.max(0, Math.round(sleepUntil - (Date.now() / 1000)));
            if ((!Number.isFinite(sleepSeconds) || sleepSeconds <= 0) && remaining > 0) {
                sleepSeconds = remaining;
            }
            if (remaining > 0) {
                wakeTimeText = new Date(sleepUntil * 1000).toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
            }
        }

        label = 'Deep Sleep';
        const hasSleepDuration = Number.isFinite(sleepSeconds) && sleepSeconds > 0;
        const hasWakeEstimate = wakeTimeText !== '';
        detailText = hasSleepDuration || hasWakeEstimate
            ? buildDeepSleepDetail(sleepSeconds, wakeTimeText)
            : buildDeepSleepDetail(0, '');
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700';
        state[kebun].espMainState = 'sleeping';
    }

    badge.textContent = label;
    badge.className = badgeClass;
    detail.textContent = detailText;
    persistUiState();
}

async function hydrateUiStateFromBackend() {
    try {
        const response = await fetch('/api/device-runtime-state', { cache: 'no-store' });
        if (!response.ok) return;

        const runtimeMap = await response.json();
        if (!runtimeMap || typeof runtimeMap !== 'object') return;

        Object.keys(kebunConfig).forEach((kebun) => {
            applyRuntimeStateFromBackend(kebun, runtimeMap[kebun] || null);
        });
    } catch (_) {
        // ignore fetch errors; live MQTT updates can still refresh the UI afterward
    }
}

function normalizeKebun(value) {
    if (!value) return null;
    return deviceToUi[String(value).toLowerCase()] || null;
}

function statusEl(kebun) {
    return document.getElementById(`status-${kebun}`);
}

function countdownEl(kebun) {
    return document.getElementById(`countdown-${kebun}`);
}

function modeEl(kebun) {
    return document.getElementById(`mode-${kebun}`);
}

function espStateEl(kebun) {
    return document.getElementById(`esp-state-${kebun}`);
}

function espDetailEl(kebun) {
    return document.getElementById(`esp-detail-${kebun}`);
}

function setMode(kebun, mode) {
    if (!state[kebun]) return;
    const normalized = String(mode || '').toLowerCase();
    state[kebun].mode = normalized || 'unknown';

    const el = modeEl(kebun);
    if (!el) return;

    if (normalized === 'mode_cal') {
        el.textContent = 'MODE_CAL';
        el.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700';
        persistUiState();
        return;
    }

    if (normalized === 'mode_auto') {
        el.textContent = 'MODE_AUTO';
        el.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700';
        persistUiState();
        return;
    }

    el.textContent = 'UNKNOWN';
    el.className = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700';
    persistUiState();
}

function setStatus(kebun, text, isError = false) {
    const el = statusEl(kebun);
    if (!el) return;
    el.textContent = text;
    el.classList.remove('text-slate-600', 'text-green-700', 'text-red-600');
    el.classList.add(isError ? 'text-red-600' : 'text-green-700');
}

function formatWakeEstimate(seconds) {
    if (!Number.isFinite(seconds) || seconds <= 0) return '';
    const eta = new Date(Date.now() + (seconds * 1000));
    return eta.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function setEspState(kebun, status, payload = null) {
    const badge = espStateEl(kebun);
    const detail = espDetailEl(kebun);
    if (!badge || !detail || !state[kebun]) return;

    const statusLc = String(status || '').trim().toLowerCase();
    let label = 'UNKNOWN';
    let detailText = 'Menunggu status dari ESP...';
    let badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700';

    if (statusLc === 'power_on_or_reset') {
        label = 'Menyala / Restart';
        detailText = 'ESP baru boot atau reset.';
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700';
        state[kebun].espMainState = 'boot';
        setDummyDeviceState(kebun, 'boot');
    } else if (statusLc === 'wake_up_from_deep_sleep') {
        label = 'Bangun dari Sleep';
        detailText = 'ESP aktif kembali setelah deep sleep.';
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700';
        state[kebun].espMainState = 'awake';
        setDummyDeviceState(kebun, 'awake');
    } else if (statusLc === 'going_to_sleep') {
        const sleepSeconds = Number(payload?.sleepSeconds ?? payload?.tSleep ?? payload?.sleep_seconds ?? 0);
        const eta = formatWakeEstimate(sleepSeconds);

        label = 'Deep Sleep';
        detailText = buildDeepSleepDetail(sleepSeconds, eta);
        badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700';
        state[kebun].espMainState = 'sleeping';
        setDummyDeviceState(kebun, 'sleeping', { sleepSeconds });
    } else if (statusLc === 'device_connected') {
        if (state[kebun].espMainState === 'unknown') {
            label = 'MQTT Connected';
            badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-cyan-100 text-cyan-700';
        } else if (state[kebun].espMainState === 'sleeping') {
            label = 'Deep Sleep';
            badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700';
        } else if (state[kebun].espMainState === 'boot') {
            label = 'Menyala / Restart';
            badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700';
        } else {
            label = 'Bangun dari Sleep';
            badgeClass = 'text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700';
        }
        detailText = 'Koneksi MQTT ke broker aktif.';
        if (state[kebun].power.deviceState === 'boot') {
            setDummyDeviceState(kebun, 'awake');
        }
    } else {
        return;
    }

    badge.textContent = label;
    badge.className = badgeClass;
    detail.textContent = detailText;
    persistUiState();
}

function setBusy(kebun, busy) {
    state[kebun].busy = busy;
    const controls = document.querySelectorAll(`[data-kebun-action="${kebun}"]`);
    controls.forEach((el) => {
        el.disabled = busy;
        el.classList.toggle('opacity-60', busy);
        el.classList.toggle('cursor-not-allowed', busy);
    });

    const modeControls = document.querySelectorAll(`[data-kebun-mode="${kebun}"]`);
    modeControls.forEach((el) => {
        el.disabled = busy;
        el.classList.toggle('opacity-60', busy);
        el.classList.toggle('cursor-not-allowed', busy);
    });

    const tdsInput = document.getElementById(`tds-ppm-${kebun}`);
    const tempInput = document.getElementById(`temp-offset-${kebun}`);
    if (tdsInput) tdsInput.disabled = busy;
    if (tempInput) tempInput.disabled = busy;
}

function clearWaiting(kebun) {
    const current = state[kebun];
    current.waiting = null;

    if (current.timeoutId) {
        clearTimeout(current.timeoutId);
        current.timeoutId = null;
    }

    if (current.countdownId) {
        clearInterval(current.countdownId);
        current.countdownId = null;
    }

    const countdown = countdownEl(kebun);
    if (countdown) {
        countdown.classList.add('hidden');
        countdown.textContent = '';
    }

    setBusy(kebun, false);
}

function runCountdown(kebun, seconds) {
    const countdown = countdownEl(kebun);
    if (!countdown) return;

    let remaining = seconds;
    countdown.classList.remove('hidden');
    countdown.textContent = `Countdown pH: ${remaining} detik`;

    if (state[kebun].countdownId) {
        clearInterval(state[kebun].countdownId);
    }

    state[kebun].countdownId = setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
            countdown.textContent = 'Countdown selesai, menunggu status simpan...';
            clearInterval(state[kebun].countdownId);
            state[kebun].countdownId = null;
            return;
        }
        countdown.textContent = `Countdown pH: ${remaining} detik`;
    }, 1000);
}

function getStatusValue(payload) {
    if (typeof payload === 'string') {
        return payload.trim();
    }

    if (payload && typeof payload === 'object') {
        if (typeof payload.status === 'string') return payload.status.trim();
        if (typeof payload.message === 'string') return payload.message.trim();
    }

    return '';
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

    if (
        text.includes('mode_cal') ||
        text.includes('mode cal') ||
        text.includes('calibration mode') ||
        text.includes('mode_changed:mode_cal') ||
        text.includes('mode_set:mode_cal')
    ) {
        return 'mode_cal';
    }

    if (
        text.includes('mode_auto') ||
        text.includes('mode auto') ||
        text.includes('normal mode') ||
        text.includes('mode_changed:mode_auto') ||
        text.includes('mode_set:mode_auto')
    ) {
        return 'mode_auto';
    }

    return null;
}

function randomFloat(min, max) {
    return min + (Math.random() * (max - min));
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function setDummyDeviceState(kebun, nextState, options = {}) {
    if (!state[kebun] || !state[kebun].power) return;
    const powerState = state[kebun].power;
    const allowed = ['boot', 'awake', 'active', 'sleeping'];
    if (!allowed.includes(nextState)) return;

    powerState.deviceState = nextState;

    if (nextState === 'sleeping') {
        const sleepSeconds = Number(options.sleepSeconds ?? 0);
        if (Number.isFinite(sleepSeconds) && sleepSeconds > 0) {
            powerState.sleepSeconds = sleepSeconds;
            powerState.wakeEstimate = Date.now() + (sleepSeconds * 1000);
        } else {
            powerState.sleepSeconds = null;
            powerState.wakeEstimate = null;
        }
    } else {
        powerState.sleepSeconds = null;
        powerState.wakeEstimate = null;
    }
}

function getPowerVisualState(kebun) {
    const currentMode = state[kebun]?.mode || 'unknown';
    const currentState = state[kebun]?.power?.deviceState || 'boot';

    if (currentMode === 'mode_cal') {
        return {
            key: 'calibration',
            label: 'CALIBRATION',
            className: 'text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700',
        };
    }

    if (currentState === 'sleeping') {
        return {
            key: 'sleeping',
            label: 'SLEEPING',
            className: 'text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700',
        };
    }

    if (currentState === 'boot') {
        return {
            key: 'boot',
            label: 'BOOT',
            className: 'text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700',
        };
    }

    if (currentState === 'awake') {
        return {
            key: 'awake',
            label: 'AWAKE',
            className: 'text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700',
        };
    }

    return {
        key: 'active',
        label: 'ACTIVE',
        className: 'text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700',
    };
}

function drawSparkline(canvasId, values, lineColor) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;

    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, w, h);

    if (!Array.isArray(values) || values.length < 2) return;

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const padX = 4;
    const padY = 6;
    const usableW = w - (padX * 2);
    const usableH = h - (padY * 2);

    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(padX, h - padY);
    ctx.lineTo(w - padX, h - padY);
    ctx.stroke();

    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 2;
    ctx.beginPath();

    values.forEach((value, index) => {
        const x = padX + ((values.length === 1 ? 0 : index / (values.length - 1)) * usableW);
        const y = (h - padY) - (((value - min) / range) * usableH);
        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    });

    ctx.stroke();
}

function generatePowerDummy(kebun) {
    if (!state[kebun] || !state[kebun].power) return null;
    const powerState = state[kebun].power;
    const deviceMode = state[kebun].mode === 'mode_cal' ? 'CALIBRATION' : 'AUTO';
    const now = Date.now();

    let target;
    let jitter;
    let spikeChance;
    let spikeRange;

    if (powerState.deviceState === 'sleeping') {
        target = randomFloat(1.5, 3.0);
        jitter = randomFloat(-0.08, 0.08);
        spikeChance = 0;
        spikeRange = [0, 0];
    } else if (deviceMode === 'CALIBRATION') {
        target = randomFloat(90, 130);
        jitter = randomFloat(-2, 2);
        spikeChance = 0.01;
        spikeRange = [8, 16];
    } else if (powerState.deviceState === 'boot') {
        target = randomFloat(80, 110);
        jitter = randomFloat(-8, 8);
        spikeChance = 0.08;
        spikeRange = [20, 40];
    } else {
        target = randomFloat(80, 240);
        jitter = randomFloat(-4, 4);
        spikeChance = 0.05;
        spikeRange = [20, 40];
    }

    let current = (powerState.prevCurrent * 0.65) + (target * 0.35) + jitter;

    if (now <= powerState.mqttBurstUntil) {
        current += randomFloat(10, 24);
    }

    if (Math.random() < spikeChance) {
        current += randomFloat(spikeRange[0], spikeRange[1]);
    }

    if (powerState.deviceState === 'sleeping') {
        current = clamp(current, 1.5, 3.0);
    } else if (deviceMode === 'CALIBRATION') {
        current = clamp(current, 88, 140);
    } else if (powerState.deviceState === 'boot') {
        current = clamp(current, 70, 150);
    } else {
        current = clamp(current, 70, 260);
    }

    const voltage = clamp(randomFloat(3.6, 3.9), 3.6, 3.9);
    const powermW = current * voltage;

    powerState.prevCurrent = current;

    return {
        source: 'dummy_power',
        deviceState: powerState.deviceState,
        mode: deviceMode,
        currentmA: Number(current.toFixed(2)),
        voltage: Number(voltage.toFixed(2)),
        powermW: Number(powermW.toFixed(2)),
        estimated: true,
        timestamp: Math.floor(now / 1000),
    };
}

function renderDummyPower(kebun, payload) {
    if (!payload || !state[kebun] || !state[kebun].power) return;
    const powerState = state[kebun].power;

    powerState.currentHistory.push(payload.currentmA);
    powerState.powerHistory.push(payload.powermW);
    if (powerState.currentHistory.length > 45) powerState.currentHistory.shift();
    if (powerState.powerHistory.length > 45) powerState.powerHistory.shift();
    powerState.lastPayload = payload;

    const visual = getPowerVisualState(kebun);
    const badge = document.getElementById(`power-state-${kebun}`);
    if (badge) {
        badge.textContent = visual.label;
        badge.className = visual.className;
    }

    const currentEl = document.getElementById(`power-current-${kebun}`);
    const voltageEl = document.getElementById(`power-voltage-${kebun}`);
    const powerEl = document.getElementById(`power-value-${kebun}`);
    const metaEl = document.getElementById(`power-meta-${kebun}`);

    if (currentEl) currentEl.textContent = `${payload.currentmA.toFixed(2)} mA`;
    if (voltageEl) voltageEl.textContent = `${payload.voltage.toFixed(2)} V`;
    if (powerEl) powerEl.textContent = `${payload.powermW.toFixed(2)} mW`;

    if (metaEl) {
        let extra = 'Source: dummy_power | estimated=true';
        if (powerState.deviceState === 'sleeping' && powerState.wakeEstimate) {
            const eta = new Date(powerState.wakeEstimate).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
            extra += ` | wake_estimate=${eta}`;
        }
        metaEl.textContent = extra;
    }

    drawSparkline(`power-current-chart-${kebun}`, powerState.currentHistory, '#16a34a');
    drawSparkline(`power-value-chart-${kebun}`, powerState.powerHistory, '#0284c7');

    window.dispatchEvent(new CustomEvent('dummy-power-update', { detail: { kebun, payload } }));
}

function startDummyPowerLoop() {
    if (dummyPowerTimer) return;

    Object.keys(kebunConfig).forEach((kebun) => {
        const initialPayload = generatePowerDummy(kebun);
        if (initialPayload) {
            renderDummyPower(kebun, initialPayload);
        }
    });

    dummyPowerTimer = setInterval(() => {
        Object.keys(kebunConfig).forEach((kebun) => {
            const payload = generatePowerDummy(kebun);
            if (payload) {
                renderDummyPower(kebun, payload);
            }
        });
    }, 1000);
}

function showInfoAlert(title, text, icon = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon, title, text, confirmButtonColor: '#16a34a' });
    } else {
        alert(`${title}: ${text}`);
    }
}

function publishCommand(kebun, command, options = {}) {
    if (!mqttClient || !mqttClient.connected) {
        showInfoAlert('MQTT Belum Terkoneksi', 'Periksa koneksi broker dan coba lagi.', 'error');
        return;
    }

    if (state[kebun].busy) {
        showInfoAlert('Proses Berjalan', `${kebunConfig[kebun].label} masih menjalankan command sebelumnya.`, 'warning');
        return;
    }

    const device = kebunConfig[kebun].device;
    const topic = `hidroganik/${device}/command`;
    const waitFor = (options.waitFor || []).map((s) => s.toLowerCase());
    const failFor = (options.failFor || []).map((s) => s.toLowerCase());
    const timeoutMs = options.timeoutMs || 30000;

    setBusy(kebun, true);
    setStatus(kebun, `Mengirim command: ${command}`);

    state[kebun].waiting = {
        command,
        waitFor,
        failFor,
        onSuccess: options.onSuccess,
    };

    if (options.countdown === true) {
        runCountdown(kebun, 15);
    }

    mqttClient.publish(topic, command, { qos: 0 }, (err) => {
        if (err) {
            clearWaiting(kebun);
            setStatus(kebun, `Gagal publish command: ${err.message}`, true);
            return;
        }

        if (!waitFor.length && !failFor.length) {
            setTimeout(() => {
                clearWaiting(kebun);
                setStatus(kebun, `Command terkirim: ${command}`);
            }, 800);
            return;
        }

        state[kebun].timeoutId = setTimeout(() => {
            clearWaiting(kebun);
            setStatus(kebun, 'Timeout: status belum diterima dari ESP32.', true);
        }, timeoutMs);
    });
}

function ensureCalMode(kebun, actionLabel) {
    if (!state[kebun]) return false;
    if (state[kebun].mode === 'mode_cal') return true;

    showInfoAlert(
        'Mode Belum Sesuai',
        `${kebunConfig[kebun].label}: ${actionLabel} hanya bisa dijalankan di MODE_CAL. Ubah mode dulu.`,
        'warning'
    );
    return false;
}

function setModeCal(kebun) {
    publishCommand(kebun, 'MODE_CAL', {
        waitFor: statusOkMap.MODE_CAL,
        timeoutMs: 15000,
        onSuccess: () => {
            setMode(kebun, 'mode_cal');
        },
    });
}

function setModeAuto(kebun) {
    publishCommand(kebun, 'MODE_AUTO', {
        waitFor: statusOkMap.MODE_AUTO,
        timeoutMs: 15000,
        onSuccess: () => {
            setMode(kebun, 'mode_auto');
        },
    });
}

function processStatus(topicKebun, payload) {
    const kebun = normalizeKebun(topicKebun) || normalizeKebun(payload.kebun || payload.perangkat);
    if (!kebun || !state[kebun]) return;

    const status = getStatusValue(payload);
    if (!status) return;

    const modeFromStatus = parseModeFromAny(payload) || parseModeFromAny(status);
    if (modeFromStatus) {
        setMode(kebun, modeFromStatus);
    }

    setEspState(kebun, status, payload);

    setStatus(kebun, `Status: ${status}`);

    const waiting = state[kebun].waiting;
    if (!waiting) return;

    const statusLc = status.toLowerCase();
    if (waiting.waitFor.includes(statusLc)) {
        if (typeof waiting.onSuccess === 'function') {
            waiting.onSuccess();
        }
        clearWaiting(kebun);
        const successTitle = waiting.command.startsWith('MODE_') ? 'Mode Berhasil Diubah' : 'Kalibrasi Berhasil';
        showInfoAlert(successTitle, `${kebunConfig[kebun].label}: ${status}`, 'success');
        return;
    }

    if (waiting.failFor.includes(statusLc)) {
        clearWaiting(kebun);
        showInfoAlert('Kalibrasi Gagal', `${kebunConfig[kebun].label}: ${status}`, 'error');
    }
}

function updatePreview(kebunFromTopic, data) {
    const kebun = normalizeKebun(kebunFromTopic);
    if (!kebun) return;

    if (state[kebun]?.power) {
        state[kebun].power.lastSensorAt = Date.now();
        state[kebun].power.mqttBurstUntil = Date.now() + 2200;
        setDummyDeviceState(kebun, 'active');
    }

    const modeFromPayload = parseModeFromAny(data);
    if (modeFromPayload) {
        setMode(kebun, modeFromPayload);
    }

    const tds = data.tds;
    const suhu = data.suhu_air ?? data.suhu;
    const suhuMentah = data.suhu_mentah ?? data.suhuRaw ?? data.suhu_raw;
    const phVolt401 = data.phAcidVoltage ?? data.ph_acid_voltage;
    const phVolt686 = data.phNeutralVoltage ?? data.ph_neutral_voltage;

    const tdsCalEl = document.getElementById(`${kebun}-tds-kalibrasi`);
    const suhuCalEl = document.getElementById(`${kebun}-suhu-kalibrasi`);
    const suhuOffsetEl = document.getElementById(`${kebun}-offset-suhu`);
    const suhuOffsetRawEl = document.getElementById(`${kebun}-offset-suhu-raw`);
    const ph401VoltEl = document.getElementById(`${kebun}-ph401-volt`);
    const ph686VoltEl = document.getElementById(`${kebun}-ph686-volt`);

    if (tds !== null && tds !== undefined && tdsCalEl) {
        tdsCalEl.textContent = `${Math.round(Number(tds))} ppm`;
    }
    if (suhu !== null && suhu !== undefined && suhuCalEl) {
        suhuCalEl.textContent = `${Number(suhu).toFixed(1)} °C`;
    }
    if (suhu !== null && suhu !== undefined && suhuOffsetEl) {
        suhuOffsetEl.textContent = Number(suhu).toFixed(1);
    }
    if (suhuMentah !== null && suhuMentah !== undefined && suhuOffsetRawEl) {
        suhuOffsetRawEl.textContent = Number(suhuMentah).toFixed(1);
    }
    if (phVolt401 !== null && phVolt401 !== undefined && ph401VoltEl) {
        ph401VoltEl.textContent = Number(phVolt401).toFixed(4);
    }
    if (phVolt686 !== null && phVolt686 !== undefined && ph686VoltEl) {
        ph686VoltEl.textContent = Number(phVolt686).toFixed(4);
    }
}

function connectMQTT() {
    mqttClient = mqtt.connect(mqttBroker, {
        clientId: 'hidroponik_kalibrasi_' + Math.random().toString(16).slice(2, 10),
        clean: true,
        reconnectPeriod: 2000,
        keepalive: 60,
    });

    mqttClient.on('connect', () => {
        Object.keys(kebunConfig).forEach((kebun) => {
            setStatus(kebun, 'MQTT terhubung. Siap mengirim command.');
        });

        mqttClient.subscribe('hidroganik/+/preview', (err) => {
            if (err) {
                Object.keys(kebunConfig).forEach((kebun) => {
                    setStatus(kebun, 'Gagal subscribe preview topic.', true);
                });
            }
        });

        mqttClient.subscribe('hidroganik/+/publish', (err) => {
            if (err) {
                Object.keys(kebunConfig).forEach((kebun) => {
                    setStatus(kebun, 'Gagal subscribe publish topic.', true);
                });
            }
        });

        mqttClient.subscribe('hidroganik/+/status', (err) => {
            if (err) {
                Object.keys(kebunConfig).forEach((kebun) => {
                    setStatus(kebun, 'Gagal subscribe status topic.', true);
                });
            }
        });
    });

    mqttClient.on('message', (topic, message) => {
        const parts = topic.split('/');
        const topicKebun = parts[1] || null;
        const kind = parts[2] || '';

        if (kind === 'preview' || kind === 'publish') {
            try {
                const data = JSON.parse(message.toString());
                updatePreview(topicKebun, data);
            } catch (error) {
                console.error('[Kalibrasi] Preview parse error:', error);
            }
            return;
        }

        if (kind === 'status') {
            const raw = message.toString().trim();
            let payload = raw;
            try {
                payload = JSON.parse(raw);
            } catch (_) {
                // Status valid juga dalam bentuk string, jadi parse error diabaikan.
            }
            processStatus(topicKebun, payload);
        }
    });

    mqttClient.on('error', (error) => {
        Object.keys(kebunConfig).forEach((kebun) => {
            setStatus(kebun, `MQTT error: ${error.message}`, true);
        });
    });

    mqttClient.on('offline', () => {
        Object.keys(kebunConfig).forEach((kebun) => {
            setStatus(kebun, 'MQTT offline, menunggu reconnect...', true);
        });
    });
}

function startPh401(kebun) {
    if (!ensureCalMode(kebun, 'Kalibrasi pH 4.01')) return;
    publishCommand(kebun, 'PH401_START', {
        waitFor: statusOkMap.PH401_START,
        countdown: true,
        timeoutMs: 35000,
    });
}

function startPh686(kebun) {
    if (!ensureCalMode(kebun, 'Kalibrasi pH 6.86')) return;
    publishCommand(kebun, 'PH686_START', {
        waitFor: statusOkMap.PH686_START,
        countdown: true,
        timeoutMs: 35000,
    });
}

function submitTds(kebun) {
    if (!ensureCalMode(kebun, 'Kalibrasi TDS')) return;
    const input = document.getElementById(`tds-ppm-${kebun}`);
    const value = Number(input.value);

    if (!Number.isFinite(value) || value <= 0) {
        showInfoAlert('Input Tidak Valid', 'Masukkan nilai TDS referensi (ppm) lebih dari 0.', 'warning');
        return;
    }

    publishCommand(kebun, `TDS:${Math.round(value)}`, {
        waitFor: statusOkMap.TDS,
        failFor: statusFailMap.TDS,
        timeoutMs: 20000,
    });
}

function submitTempOffset(kebun) {
    if (!ensureCalMode(kebun, 'Set offset suhu')) return;
    const input = document.getElementById(`temp-offset-${kebun}`);
    const value = Number(input.value);

    if (!Number.isFinite(value)) {
        showInfoAlert('Input Tidak Valid', 'Masukkan offset suhu yang valid (contoh 1.5 atau -1).', 'warning');
        return;
    }

    publishCommand(kebun, `TEMP_OFFSET:${value}`, {
        waitFor: statusOkMap.TEMP_OFFSET,
        timeoutMs: 20000,
    });
}

function sendQuickCommand(kebun, command) {
    if (!ensureCalMode(kebun, `Command ${command}`)) return;
    publishCommand(kebun, command, {
        timeoutMs: 12000,
    });
}

hydrateUiStateFromBackend();
connectMQTT();
</script>
@endsection
