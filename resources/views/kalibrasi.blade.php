@extends('layouts.app')

@section('content')
<div class="w-full py-8 px-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- KEBUN A CARD -->
        <div class="bg-white shadow-md rounded-lg p-6 w-full max-w-full">
            <h2 class="text-2xl font-bold text-[var(--color-text-main)] mb-2">Kebun A</h2>
            <div class="text-gray-500 mb-6">Kalibrasi & Live Preview</div>
            
            <!-- Live Data Preview - Enhanced Design -->
            <div class="grid grid-cols-2 gap-3 mb-6">
                <!-- TDS Card -->
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
                            <div id="kebun-a-tds-kalibrasi" class="text-xl sm:text-2xl font-bold text-green-700">--</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-2 border border-green-100">
                            <div class="text-xs text-green-600 mb-1">Data Mentah</div>
                            <div id="kebun-a-tds-mentah" class="text-xl sm:text-2xl font-bold text-green-600">--</div>
                        </div>
                    </div>
                </div>

                <!-- Suhu Card -->
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
                            <div id="kebun-a-suhu-kalibrasi" class="text-xl sm:text-2xl font-bold text-green-700">--</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-2 border border-green-100">
                            <div class="text-xs text-green-600 mb-1">Data Mentah</div>
                            <div id="kebun-a-suhu-mentah" class="text-xl sm:text-2xl font-bold text-green-600">--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calibration Settings - 2 Columns Layout -->
            <div class="grid grid-cols-2 gap-2.5">
                <!-- TDS Calibration -->
                <div class="border border-green-200 rounded-lg p-2.5 bg-green-50">
                    <h4 class="font-semibold text-green-700 mb-2 flex items-center gap-1 text-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Kalibrasi TDS
                    </h4>
                    
                    <div class="mb-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Multiplier</label>
                        <input type="number" step="0.0001" id="tds-mult-a" value="{{ $settings['kebun-a']->tds_multiplier ?? 1.0 }}"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <div class="text-xs text-gray-500 mt-0.5" style="font-size: 10px;">Formula: TDS × Multiplier</div>
                    </div>

                    <div class="bg-white rounded p-2 mb-2">
                        <div class="text-xs font-medium text-gray-600 mb-1">Test</div>
                        <div class="flex gap-1 items-stretch">
                            <input type="number" id="tds-raw-a" placeholder="Raw"
                                   class="flex-1 min-w-0 border border-gray-300 rounded px-1.5 py-1 text-xs text-gray-900 placeholder-gray-400" style="font-size: 11px;">
                            <button onclick="testTDS('kebun-a')" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition whitespace-nowrap flex-shrink-0">
                                Test
                            </button>
                        </div>
                        <div id="tds-result-a" class="text-xs text-gray-600 mt-1 break-words" style="font-size: 10px;"></div>
                    </div>

                    <button onclick="saveTDS('kebun-a')" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 rounded transition shadow-sm text-xs">
                        Simpan
                    </button>
                </div>

                <!-- Suhu Calibration -->
                <div class="border border-green-200 rounded-lg p-2.5 bg-green-50">
                    <h4 class="font-semibold text-green-700 mb-2 flex items-center gap-1 text-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Kalibrasi Suhu
                    </h4>
                    
                    <div class="mb-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Correction</label>
                        <input type="number" step="0.01" id="suhu-corr-a" value="{{ $settings['kebun-a']->suhu_correction ?? 0.0 }}"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <div class="text-xs text-gray-500 mt-0.5" style="font-size: 10px;">Formula: Suhu + Correction</div>
                    </div>

                    <div class="bg-white rounded p-2 mb-2">
                        <div class="text-xs font-medium text-gray-600 mb-1">Test</div>
                        <div class="flex gap-1 items-stretch">
                            <input type="number" step="0.01" id="suhu-raw-a" placeholder="Raw"
                                   class="flex-1 min-w-0 border border-gray-300 rounded px-1.5 py-1 text-xs text-gray-900 placeholder-gray-400" style="font-size: 11px;">
                            <button onclick="testSuhu('kebun-a')" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition whitespace-nowrap flex-shrink-0">
                                Test
                            </button>
                        </div>
                        <div id="suhu-result-a" class="text-xs text-gray-600 mt-1 break-words" style="font-size: 10px;"></div>
                    </div>

                    <button onclick="saveSuhu('kebun-a')" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 rounded transition shadow-sm text-xs">
                        Simpan
                    </button>
                </div>
            </div>
        </div>

        <!-- KEBUN B CARD -->
        <div class="bg-white shadow-md rounded-lg p-6 w-full max-w-full">
            <h2 class="text-2xl font-bold text-[var(--color-text-main)] mb-2">Kebun B</h2>
            <div class="text-gray-500 mb-6">Kalibrasi & Live Preview</div>
            
            <!-- Live Data Preview - Enhanced Design -->
            <div class="grid grid-cols-2 gap-3 mb-6">
                <!-- TDS Card -->
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
                            <div id="kebun-b-tds-kalibrasi" class="text-xl sm:text-2xl font-bold text-green-700">--</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-2 border border-green-100">
                            <div class="text-xs text-green-600 mb-1">Data Mentah</div>
                            <div id="kebun-b-tds-mentah" class="text-xl sm:text-2xl font-bold text-green-600">--</div>
                        </div>
                    </div>
                </div>

                <!-- Suhu Card -->
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
                            <div id="kebun-b-suhu-kalibrasi" class="text-xl sm:text-2xl font-bold text-green-700">--</div>
                        </div>
                        <div class="bg-white/50 rounded-lg p-2 border border-green-100">
                            <div class="text-xs text-green-600 mb-1">Data Mentah</div>
                            <div id="kebun-b-suhu-mentah" class="text-xl sm:text-2xl font-bold text-green-600">--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calibration Settings - 2 Columns Layout -->
            <div class="grid grid-cols-2 gap-2.5">
                <!-- TDS Calibration -->
                <div class="border border-green-200 rounded-lg p-2.5 bg-green-50">
                    <h4 class="font-semibold text-green-700 mb-2 flex items-center gap-1 text-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        Kalibrasi TDS
                    </h4>
                    
                    <div class="mb-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Multiplier</label>
                        <input type="number" step="0.0001" id="tds-mult-b" value="{{ $settings['kebun-b']->tds_multiplier ?? 1.0 }}"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <div class="text-xs text-gray-500 mt-0.5" style="font-size: 10px;">Formula: TDS × Multiplier</div>
                    </div>

                    <div class="bg-white rounded p-2 mb-2">
                        <div class="text-xs font-medium text-gray-600 mb-1">Test</div>
                        <div class="flex gap-1 items-stretch">
                            <input type="number" id="tds-raw-b" placeholder="Raw"
                                   class="flex-1 min-w-0 border border-gray-300 rounded px-1.5 py-1 text-xs text-gray-900 placeholder-gray-400" style="font-size: 11px;">
                            <button onclick="testTDS('kebun-b')" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition whitespace-nowrap flex-shrink-0">
                                Test
                            </button>
                        </div>
                        <div id="tds-result-b" class="text-xs text-gray-600 mt-1 break-words" style="font-size: 10px;"></div>
                    </div>

                    <button onclick="saveTDS('kebun-b')" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 rounded transition shadow-sm text-xs">
                        Simpan
                    </button>
                </div>

                <!-- Suhu Calibration -->
                <div class="border border-green-200 rounded-lg p-2.5 bg-green-50">
                    <h4 class="font-semibold text-green-700 mb-2 flex items-center gap-1 text-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Kalibrasi Suhu
                    </h4>
                    
                    <div class="mb-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Correction</label>
                        <input type="number" step="0.01" id="suhu-corr-b" value="{{ $settings['kebun-b']->suhu_correction ?? 0.0 }}"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <div class="text-xs text-gray-500 mt-0.5" style="font-size: 10px;">Formula: Suhu + Correction</div>
                    </div>

                    <div class="bg-white rounded p-2 mb-2">
                        <div class="text-xs font-medium text-gray-600 mb-1">Test</div>
                        <div class="flex gap-1 items-stretch">
                            <input type="number" step="0.01" id="suhu-raw-b" placeholder="Raw"
                                   class="flex-1 min-w-0 border border-gray-300 rounded px-1.5 py-1 text-xs text-gray-900 placeholder-gray-400" style="font-size: 11px;">
                            <button onclick="testSuhu('kebun-b')" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition whitespace-nowrap flex-shrink-0">
                                Test
                            </button>
                        </div>
                        <div id="suhu-result-b" class="text-xs text-gray-600 mt-1 break-words" style="font-size: 10px;"></div>
                    </div>

                    <button onclick="saveSuhu('kebun-b')" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 rounded transition shadow-sm text-xs">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script>
// MQTT Client for Kalibrasi page - subscribe to PREVIEW topic with raw data
const mqttBroker = 'ws://broker.emqx.io:8083/mqtt';
let mqttClient = null;

function connectMQTT() {
    try {
        mqttClient = mqtt.connect(mqttBroker, {
            clientId: 'hidroponik_kalibrasi_' + Math.random().toString(16).substr(2, 8),
            clean: true,
            reconnectPeriod: 2000,
            keepalive: 60,
        });

        mqttClient.on('connect', () => {
            console.log('[Kalibrasi] MQTT Connected');
            // Subscribe to PREVIEW topic - ini sudah include data mentah dan terkalibrasi
            mqttClient.subscribe('hidroganik/+/preview', (err) => {
                if (err) console.error('[Kalibrasi] Subscribe preview error:', err);
                else console.log('[Kalibrasi] Subscribed to hidroganik/+/preview');
            });
        });

        mqttClient.on('message', (topic, message) => {
            try {
                const data = JSON.parse(message.toString());
                const parts = topic.split('/');
                const kebun = parts[1];
                
                console.log('[Kalibrasi] Received preview data:', kebun, data);
                
                if (kebun === 'kebun-a') {
                    updateKebunA(data);
                } else if (kebun === 'kebun-b') {
                    updateKebunB(data);
                }
            } catch (e) {
                console.error('[Kalibrasi] Parse error:', e);
            }
        });

        mqttClient.on('error', (err) => {
            console.error('[Kalibrasi] MQTT error:', err);
        });
    } catch (e) {
        console.error('[Kalibrasi] MQTT init failed:', e);
    }
}

function updateKebunA(data) {
    // TDS Terkalibrasi
    if (data.tds !== null && data.tds !== undefined) {
        document.getElementById('kebun-a-tds-kalibrasi').textContent = Math.round(data.tds) + ' ppm';
    }
    
    // Suhu Terkalibrasi
    if (data.suhu_air !== null && data.suhu_air !== undefined) {
        document.getElementById('kebun-a-suhu-kalibrasi').textContent = data.suhu_air.toFixed(1) + ' °C';
    }
    
    // TDS Mentah - langsung dari MQTT preview
    if (data.tds_mentah !== null && data.tds_mentah !== undefined) {
        document.getElementById('kebun-a-tds-mentah').textContent = Math.round(data.tds_mentah) + ' ppm';
    }
    
    // Suhu Mentah - langsung dari MQTT preview
    if (data.suhu_mentah !== null && data.suhu_mentah !== undefined) {
        document.getElementById('kebun-a-suhu-mentah').textContent = Number(data.suhu_mentah).toFixed(1) + ' °C';
    }
}

function updateKebunB(data) {
    // TDS Terkalibrasi
    if (data.tds !== null && data.tds !== undefined) {
        document.getElementById('kebun-b-tds-kalibrasi').textContent = Math.round(data.tds) + ' ppm';
    }
    
    // Suhu Terkalibrasi
    if (data.suhu_air !== null && data.suhu_air !== undefined) {
        document.getElementById('kebun-b-suhu-kalibrasi').textContent = data.suhu_air.toFixed(1) + ' °C';
    }
    
    // TDS Mentah - langsung dari MQTT preview
    if (data.tds_mentah !== null && data.tds_mentah !== undefined) {
        document.getElementById('kebun-b-tds-mentah').textContent = Math.round(data.tds_mentah) + ' ppm';
    }
    
    // Suhu Mentah - langsung dari MQTT preview
    if (data.suhu_mentah !== null && data.suhu_mentah !== undefined) {
        document.getElementById('kebun-b-suhu-mentah').textContent = Number(data.suhu_mentah).toFixed(1) + ' °C';
    }
}

// Connect on page load
connectMQTT();

// Test TDS calibration
function testTDS(kebun) {
    const mult = parseFloat(document.getElementById('tds-mult-' + kebun.split('-')[1]).value);
    const raw = parseFloat(document.getElementById('tds-raw-' + kebun.split('-')[1]).value);
    
    if (isNaN(mult) || isNaN(raw)) {
        Swal.fire({
            icon: 'warning',
            title: 'Input Tidak Valid',
            text: 'Masukkan nilai yang valid',
            confirmButtonColor: '#16a34a'
        });
        return;
    }
    
    fetch(`/kalibrasi/${kebun}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            tds_raw: raw,
            tds_multiplier: mult,
            suhu_correction: 0
        })
    })
    .then(res => {
        if (!res.ok) throw new Error('Gagal melakukan test');
        return res.json();
    })
    .then(data => {
        const resultDiv = document.getElementById('tds-result-' + kebun.split('-')[1]);
        resultDiv.innerHTML = `<strong>Hasil:</strong> ${raw} × ${mult} = <span class="text-green-600 font-bold">${data.tds_calibrated} ppm</span>`;
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Test Gagal',
            text: err.message,
            confirmButtonColor: '#16a34a'
        });
    });
}

// Test Suhu calibration
function testSuhu(kebun) {
    const corr = parseFloat(document.getElementById('suhu-corr-' + kebun.split('-')[1]).value);
    const raw = parseFloat(document.getElementById('suhu-raw-' + kebun.split('-')[1]).value);
    
    if (isNaN(corr) || isNaN(raw)) {
        Swal.fire({
            icon: 'warning',
            title: 'Input Tidak Valid',
            text: 'Masukkan nilai yang valid',
            confirmButtonColor: '#16a34a'
        });
        return;
    }
    
    fetch(`/kalibrasi/${kebun}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            suhu_raw: raw,
            tds_multiplier: 1,
            suhu_correction: corr
        })
    })
    .then(res => {
        if (!res.ok) throw new Error('Gagal melakukan test');
        return res.json();
    })
    .then(data => {
        const resultDiv = document.getElementById('suhu-result-' + kebun.split('-')[1]);
        resultDiv.innerHTML = `<strong>Hasil:</strong> ${raw} + ${corr} = <span class="text-green-600 font-bold">${data.suhu_calibrated} °C</span>`;
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Test Gagal',
            text: err.message,
            confirmButtonColor: '#16a34a'
        });
    });
}

// Save TDS calibration
async function saveTDS(kebun) {
    const mult = parseFloat(document.getElementById('tds-mult-' + kebun.split('-')[1]).value);
    
    if (isNaN(mult)) {
        Swal.fire({
            icon: 'warning',
            title: 'Input Tidak Valid',
            text: 'Masukkan nilai yang valid',
            confirmButtonColor: '#16a34a'
        });
        return;
    }
    
    const result = await Swal.fire({
        icon: 'question',
        title: 'Simpan Kalibrasi TDS?',
        text: 'Pengaturan kalibrasi akan disimpan',
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/kalibrasi/${kebun}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            tds_multiplier: mult
        })
    })
    .then(async (res) => {
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const text = await res.text();
            throw new Error('Unexpected response: ' + text.slice(0, 100));
        }
        return res.json();
    })
    .then(data => {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Kalibrasi TDS berhasil disimpan',
            confirmButtonColor: '#16a34a'
        }).then(() => {
            location.reload();
        });
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Simpan Gagal',
            text: err.message,
            confirmButtonColor: '#16a34a'
        });
    });
}

// Save Suhu calibration
async function saveSuhu(kebun) {
    const corr = parseFloat(document.getElementById('suhu-corr-' + kebun.split('-')[1]).value);
    
    if (isNaN(corr)) {
        Swal.fire({
            icon: 'warning',
            title: 'Input Tidak Valid',
            text: 'Masukkan nilai yang valid',
            confirmButtonColor: '#16a34a'
        });
        return;
    }
    
    const result = await Swal.fire({
        icon: 'question',
        title: 'Simpan Kalibrasi Suhu?',
        text: 'Pengaturan kalibrasi akan disimpan',
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280'
    });
    
    if (!result.isConfirmed) return;
    
    fetch(`/kalibrasi/${kebun}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            suhu_correction: corr
        })
    })
    .then(async (res) => {
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const text = await res.text();
            throw new Error('Unexpected response: ' + text.slice(0, 100));
        }
        return res.json();
    })
    .then(data => {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Kalibrasi Suhu berhasil disimpan',
            confirmButtonColor: '#16a34a'
        }).then(() => {
            location.reload();
        });
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Simpan Gagal',
            text: err.message,
            confirmButtonColor: '#16a34a'
        });
    });
}
</script>
@endsection
