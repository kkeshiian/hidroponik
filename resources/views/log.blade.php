@extends('layouts.app')

@section('content')
<div class="w-full py-8 px-6">

    <div class="bg-white shadow-sm rounded-lg p-4 md:p-8 mb-6">
        <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-4 md:mb-6">Filter Data</h2>
        
        <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 lg:items-end">
            <!-- Filter Inputs - Mobile: vertical stack, Desktop: horizontal grid -->
            <div class="flex-1 space-y-4 lg:space-y-0 lg:grid lg:grid-cols-4 lg:gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
                    <input type="date" id="filter-start-date"
                              value="{{ date('Y-m-d', strtotime('-90 days')) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" style="color-scheme: light;">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
                    <input type="date" id="filter-end-date"
                              value="{{ date('Y-m-d') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" style="color-scheme: light;">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Perangkat</label>
                    <select id="filter-device" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all bg-white">
                        <option value="">Semua Perangkat</option>
                        <option value="kebun-a">Kebun A</option>
                        <option value="kebun-b">Kebun B</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Interval Sampling</label>
                    <select id="filter-interval" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all bg-white">
                        <option value="">Default (Semua Data)</option>
                        <option value="5">Per 5 Menit</option>
                        <option value="15">Per 15 Menit</option>
                        <option value="30">Per 30 Menit</option>
                        <option value="60">Per 1 Jam</option>
                        <option value="1440">Per 1 Hari</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Urutan Data</label>
                    <select id="filter-sort" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all bg-white">
                        <option value="newest">Terbaru ke Terlama</option>
                        <option value="oldest">Terlama ke Terbaru</option>
                    </select>
                </div>
            </div>
            
            <!-- Export Button -->
            <div class="lg:min-w-[180px]">
                <a href="{{ route('log.export') }}" id="history-export-link"
                   class="block w-full bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-lg shadow-sm transition-all text-center whitespace-nowrap">
                    Export CSV
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg p-6 shadow-sm mb-8">
        <h3 class="text-center text-lg font-semibold text-gray-800 mb-4">Statistik Data</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-lg p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col items-start border border-green-200">
                <div class="text-sm text-gray-600">Total Records</div>
                <div class="text-3xl font-bold text-green-600 mt-2">{{ $stats['total'] ?? '--' }}</div>
            </div>
            <div class="rounded-lg p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col items-start border border-green-200">
                <div class="text-sm text-gray-600">Avg pH</div>
                <div class="text-3xl font-bold text-green-600 mt-2">{{ $stats['avg_ph'] ?? '--' }}</div>
            </div>
            <div class="rounded-lg p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col items-start border border-green-200">
                <div class="text-sm text-gray-600">Avg TDS</div>
                <div class="text-3xl font-bold text-green-600 mt-2">{{ $stats['avg_tds'] ?? '--' }}<span class="text-lg">ppm</span></div>
            </div>
            <div class="rounded-lg p-6 bg-gradient-to-br from-green-50 to-green-100 shadow-sm flex flex-col items-start border border-green-200">
                <div class="text-sm text-gray-600">Avg Temperature</div>
                <div class="text-3xl font-bold text-green-600 mt-2">{{ $stats['avg_temp'] ?? '--' }}<span class="text-lg">°C</span></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg p-6 shadow-sm">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Data History</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse">
                <thead class="bg-green-50 border-b border-green-200">
                    <tr class="text-base font-semibold text-gray-700">
                        <th class="py-4 px-4">Tanggal</th>
                        <th class="py-4 px-4">Waktu</th>
                        <th class="py-4 px-4">Perangkat</th>
                        <th class="py-4 px-4">pH</th>
                        <th class="py-4 px-4">TDS (ppm)</th>
                        <th class="py-4 px-4">Suhu (°C)</th>
                    </tr>
                </thead>
                <tbody id="log-rows" class="text-base text-gray-700">
                    @if(!empty($rows) && count($rows) > 0)
                        @foreach($rows as $r)
                        @php
                            $datetime = $r->recorded_at ?? $r['recorded_at'] ?? '';
                            // Remove timezone and microseconds, replace T with space
                            $datetime = preg_replace('/\.\d+Z?$/', '', $datetime);
                            $datetime = str_replace(['T', 'Z'], [' ', ''], $datetime);
                            $parts = explode(' ', $datetime);
                            $date = $parts[0] ?? '';
                            $time = $parts[1] ?? '';
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-green-50 transition">
                            <td class="py-4 px-4">{{ $date }}</td>
                            <td class="py-4 px-4">{{ $time }}</td>
                            <td class="py-4 px-4">{{ $r->kebun ?? $r['kebun'] ?? '' }}</td>
                            <td class="py-4 px-4">{{ $r->ph ?? $r['ph'] ?? '--' }}</td>
                            <td class="py-4 px-4">{{ $r->tds ?? $r['tds'] ?? '--' }}</td>
                            <td class="py-4 px-4">{{ $r->suhu ?? $r['suhu'] ?? '--' }}</td>
                        </tr>
                        @endforeach
                    @else
                        <tr><td colspan="6" class="py-4 text-center text-gray-500">No records</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-gray-500" id="pagination-info">
                Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() ?? 0 }} entries
            </div>
            <div class="flex gap-2" id="pagination-buttons">
                @if($rows->onFirstPage())
                    <button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Previous</button>
                @else
                    <a href="{{ $rows->previousPageUrl() }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Previous</a>
                @endif
                
                <div class="flex gap-1">
                    @foreach(range(1, min($rows->lastPage(), 5)) as $pageNum)
                        @if($pageNum == $rows->currentPage())
                            <span class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold">{{ $pageNum }}</span>
                        @else
                            <a href="{{ $rows->url($pageNum) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">{{ $pageNum }}</a>
                        @endif
                    @endforeach
                    @if($rows->lastPage() > 5)
                        <span class="px-2 py-2 text-gray-500">...</span>
                        <a href="{{ $rows->url($rows->lastPage()) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">{{ $rows->lastPage() }}</a>
                    @endif
                </div>
                
                @if($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Next</a>
                @else
                    <button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Next</button>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg p-6 shadow-sm mt-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Power Consumption</h3>
                <div class="text-sm text-gray-500">Riwayat realtime estimasi daya.</div>
            </div>
            <a href="{{ route('log.power.export') }}" id="power-export-link"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-lg shadow-sm transition text-center">
                Download CSV Power
            </a>
        </div>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div class="sm:max-w-sm w-full">
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter Kebun</label>
                <select id="filter-power-device" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all bg-white">
                    <option value="">Semua Perangkat</option>
                    <option value="kebun-a">Kebun A</option>
                    <option value="kebun-b">Kebun B</option>
                </select>
            </div>
            <div class="text-sm text-gray-500 sm:text-right">Filter ini hanya memengaruhi tabel power di bawah.</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-sm font-semibold text-gray-700">
                        <th class="py-3 px-3">Waktu</th>
                        <th class="py-3 px-3">Perangkat</th>
                        <th class="py-3 px-3">Current (A)</th>
                        <th class="py-3 px-3">Voltage (V)</th>
                        <th class="py-3 px-3">Watt-hour (Wh)</th>
                    </tr>
                </thead>
                <tbody id="power-log-rows" class="text-sm text-gray-700">
                    <tr>
                        <td colspan="5" class="py-4 text-center text-gray-500">Belum ada data. Menunggu trigger MQTT dari perangkat...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-gray-500" id="power-pagination-info">
                Showing 0 to 0 of 0 entries
            </div>
            <div class="flex gap-2" id="power-pagination-buttons">
                <button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Previous</button>
                <div class="flex gap-1">
                    <span class="px-4 py-2 bg-slate-200 text-slate-500 rounded-lg">1</span>
                </div>
                <button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>

</div>

<script>
// Auto-refresh: poll /api/logs every 5 seconds with filters
(() => {
    const intervalMs = 5000;
    let currentPage = 1;
    let currentPowerPage = 1;

    function getDeviceFilterValue() {
        return document.getElementById('filter-device')?.value || '';
    }

    function getPowerDeviceFilterValue() {
        return document.getElementById('filter-power-device')?.value || getDeviceFilterValue();
    }

    function getSortFilterValue() {
        return document.getElementById('filter-sort')?.value || 'newest';
    }

    function syncDeviceFilters(value) {
        ['filter-device', 'filter-power-device'].forEach((id) => {
            const el = document.getElementById(id);
            if (el && el.value !== value) {
                el.value = value;
            }
        });
    }

    function updatePowerExportLink() {
        const link = document.getElementById('power-export-link');
        if (!link) return;

        const device = getPowerDeviceFilterValue();
        const startDate = document.getElementById('filter-start-date')?.value || '';
        const endDate = document.getElementById('filter-end-date')?.value || '';
        const sort = getSortFilterValue();
        const url = new URL(link.href, window.location.origin);

        if (device) {
            url.searchParams.set('device', device);
        } else {
            url.searchParams.delete('device');
        }

        if (startDate) {
            url.searchParams.set('from', startDate);
        } else {
            url.searchParams.delete('from');
        }

        if (endDate) {
            url.searchParams.set('to', endDate);
        } else {
            url.searchParams.delete('to');
        }

        if (sort) {
            url.searchParams.set('sort', sort);
        } else {
            url.searchParams.delete('sort');
        }

        link.href = url.toString();
    }

    function updateHistoryExportLink() {
        const link = document.getElementById('history-export-link');
        if (!link) return;

        const device = getDeviceFilterValue();
        const startDate = document.getElementById('filter-start-date')?.value || '';
        const endDate = document.getElementById('filter-end-date')?.value || '';
        const sort = getSortFilterValue();
        const url = new URL(link.href, window.location.origin);

        if (device) {
            url.searchParams.set('kebun', device);
        } else {
            url.searchParams.delete('kebun');
        }

        if (startDate) {
            url.searchParams.set('from', startDate);
        } else {
            url.searchParams.delete('from');
        }

        if (endDate) {
            url.searchParams.set('to', endDate);
        } else {
            url.searchParams.delete('to');
        }

        if (sort) {
            url.searchParams.set('sort', sort);
        } else {
            url.searchParams.delete('sort');
        }

        link.href = url.toString();
    }

    function formatTelemetryTimestamp(value, createdAt = null) {
        const parsed = value ? new Date(value) : null;
        const created = createdAt ? new Date(createdAt) : null;

        const hasParsed = parsed && !Number.isNaN(parsed.getTime());
        const hasCreated = created && !Number.isNaN(created.getTime());

        // Gunakan created_at bila recorded_at tidak valid atau meleset jauh.
        let effective = hasParsed ? parsed : null;
        if (hasParsed && hasCreated) {
            const diffHours = Math.abs(created.getTime() - parsed.getTime()) / 3600000;
            if (diffHours >= 4) {
                effective = created;
            }
        } else if (!hasParsed && hasCreated) {
            effective = created;
        }

        if (effective) {
            // Format ke Asia/Jakarta kemudian tambah 1 jam untuk WITA
            let formatted = effective.toLocaleString('sv-SE', {
                timeZone: 'Asia/Jakarta',
                hour12: false,
            }).replace(',', '');
            
            // Parse formatted string dan tambah 1 jam
            const parts = formatted.split(' ');
            const datePart = parts[0];
            const timePart = parts[1];
            
            if (timePart) {
                const timeComponents = timePart.split(':');
                if (timeComponents.length >= 2) {
                    let hours = parseInt(timeComponents[0], 10);
                    const minutes = timeComponents[1];
                    const seconds = timeComponents[2] || '00';
                    
                    hours = (hours + 1) % 24; // Add 1 hour, wrap around at 24
                    const hoursStr = String(hours).padStart(2, '0');
                    
                    return `${datePart} ${hoursStr}:${minutes}:${seconds}`;
                }
            }
            
            return formatted;
        }

        return (value ?? '').toString().replace(/\.\d+Z?$/, '').replace('T', ' ').replace('Z', '');
    }

    function formatPowerTimestamp(value, createdAt = null) {
        const parsed = value ? new Date(value) : null;
        const created = createdAt ? new Date(createdAt) : null;

        const hasParsed = parsed && !Number.isNaN(parsed.getTime());
        const hasCreated = created && !Number.isNaN(created.getTime());

        // Data lama sempat tersimpan dengan offset timezone. Jika beda jauh,
        // fallback ke created_at karena itu konsisten dengan waktu insert server.
        let effective = hasParsed ? parsed : null;
        if (hasParsed && hasCreated) {
            const diffHours = Math.abs(created.getTime() - parsed.getTime()) / 3600000;
            if (diffHours >= 4) {
                effective = created;
            }
        } else if (!hasParsed && hasCreated) {
            effective = created;
        }

        if (effective) {
            // Format ke Asia/Jakarta kemudian tambah 1 jam untuk WITA
            let formatted = effective.toLocaleString('sv-SE', {
                timeZone: 'Asia/Jakarta',
                hour12: false,
            }).replace(',', '');
            
            // Parse formatted string dan tambah 1 jam
            const parts = formatted.split(' ');
            const datePart = parts[0];
            const timePart = parts[1];
            
            if (timePart) {
                const timeComponents = timePart.split(':');
                if (timeComponents.length >= 2) {
                    let hours = parseInt(timeComponents[0], 10);
                    const minutes = timeComponents[1];
                    const seconds = timeComponents[2] || '00';
                    
                    hours = (hours + 1) % 24; // Add 1 hour, wrap around at 24
                    const hoursStr = String(hours).padStart(2, '0');
                    
                    return `${datePart} ${hoursStr}:${minutes}:${seconds}`;
                }
            }
            
            return formatted;
        }

        return (value ?? '').toString().replace(/\.\d+Z?$/, '').replace('T', ' ').replace('Z', '');
    }

    function renderPowerRows(rows, pagination = null) {
        const tbody = document.getElementById('power-log-rows');
        if (!tbody) return;

        const infoEl = document.getElementById('power-pagination-info');
        const buttonsEl = document.getElementById('power-pagination-buttons');

        if (pagination && infoEl) {
            infoEl.textContent = `Showing ${pagination.from ?? 0} to ${pagination.to ?? 0} of ${pagination.total ?? 0} entries`;
        }

        if (pagination && buttonsEl) {
            currentPowerPage = pagination.current_page || 1;
            const lastPage = pagination.last_page || 1;

            let buttonsHtml = '';
            if (currentPowerPage <= 1) {
                buttonsHtml += '<button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Previous</button>';
            } else {
                buttonsHtml += `<button onclick="goToPowerPage(${currentPowerPage - 1})" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">Previous</button>`;
            }

            buttonsHtml += '<div class="flex gap-1">';
            const maxButtons = Math.min(lastPage, 5);
            for (let i = 1; i <= maxButtons; i++) {
                if (i === currentPowerPage) {
                    buttonsHtml += `<span class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-semibold">${i}</span>`;
                } else {
                    buttonsHtml += `<button onclick="goToPowerPage(${i})" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">${i}</button>`;
                }
            }
            if (lastPage > 5) {
                buttonsHtml += '<span class="px-2 py-2 text-gray-500">...</span>';
                buttonsHtml += `<button onclick="goToPowerPage(${lastPage})" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">${lastPage}</button>`;
            }
            buttonsHtml += '</div>';

            if (currentPowerPage >= lastPage) {
                buttonsHtml += '<button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Next</button>';
            } else {
                buttonsHtml += `<button onclick="goToPowerPage(${currentPowerPage + 1})" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">Next</button>`;
            }

            buttonsEl.innerHTML = buttonsHtml;
        }

        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-gray-500">Belum ada data. Menunggu trigger MQTT dari perangkat...</td></tr>';
            return;
        }

        let html = '';
        rows.forEach((r) => {
            const datetime = formatPowerTimestamp(r.timestamp ?? '', r.created_at ?? null);
            html += `<tr class="border-b border-gray-100 hover:bg-slate-50 transition">` +
                `<td class="py-2 px-3">${datetime}</td>` +
                `<td class="py-2 px-3">${r.device_name ?? ''}</td>` +
                `<td class="py-2 px-3">${Number(r.current_a ?? ((Number(r.current_ma ?? 0)) / 1000)).toFixed(4)}</td>` +
                `<td class="py-2 px-3">${Number(r.voltage_v ?? 0).toFixed(2)}</td>` +
                `<td class="py-2 px-3">${Number(r.watt_hour_cumulative ?? r.watt_hour ?? 0).toFixed(5)}</td>` +
            `</tr>`;
        });

        tbody.innerHTML = html;
    }

    function render(data) {
        // update stats
        if (data.stats) {
            const statCards = document.querySelectorAll('.text-3xl.font-bold');
            if (statCards[0]) statCards[0].innerHTML = (data.stats.total ?? '--');
            if (statCards[1]) statCards[1].innerHTML = (data.stats.avg_ph ?? '--');
            if (statCards[2]) statCards[2].innerHTML = (data.stats.avg_tds ?? '--') + '<span class="text-lg">ppm</span>';
            if (statCards[3]) statCards[3].innerHTML = (data.stats.avg_temp ?? '--') + '<span class="text-lg">°C</span>';
        }

        // update rows with raw date from database
        const tbody = document.getElementById('log-rows');
        if (!tbody) return;
        let html = '';
        if (data.rows && data.rows.length) {
            data.rows.forEach(r => {
                const datetime = formatTelemetryTimestamp(r.recorded_at ?? '', r.created_at ?? null);
                const parts = datetime.split(' ');
                const date = parts[0] ?? '';
                const time = parts[1] ?? '';
                html += `<tr class="border-b border-gray-100 hover:bg-green-50 transition">` +
                    `<td class="py-4 px-4">${date}</td>` +
                    `<td class="py-4 px-4">${time}</td>` +
                    `<td class="py-4 px-4">${r.kebun ?? ''}</td>` +
                    `<td class="py-4 px-4">${r.ph ?? ''}</td>` +
                    `<td class="py-4 px-4">${r.tds ?? ''}</td>` +
                    `<td class="py-4 px-4">${r.suhu ?? ''}</td>` +
                `</tr>`;
            });
        } else {
            html = `<tr><td colspan="6" class="py-4 text-center text-gray-500">No records</td></tr>`;
        }
        tbody.innerHTML = html;
        
        // Update pagination info
        if (data.pagination) {
            const paginationInfo = document.getElementById('pagination-info');
            const paginationButtons = document.getElementById('pagination-buttons');
            
            if (paginationInfo) {
                paginationInfo.textContent = `Showing ${data.pagination.from} to ${data.pagination.to} of ${data.pagination.total} entries`;
            }
            
            if (paginationButtons) {
                currentPage = data.pagination.current_page;
                const lastPage = data.pagination.last_page;
                
                let buttonsHtml = '';
                
                // Previous button
                if (currentPage === 1) {
                    buttonsHtml += '<button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Previous</button>';
                } else {
                    buttonsHtml += `<button onclick="goToPage(${currentPage - 1})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Previous</button>`;
                }
                
                // Page numbers
                buttonsHtml += '<div class="flex gap-1">';
                const maxButtons = Math.min(lastPage, 5);
                for (let i = 1; i <= maxButtons; i++) {
                    if (i === currentPage) {
                        buttonsHtml += `<span class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold">${i}</span>`;
                    } else {
                        buttonsHtml += `<button onclick="goToPage(${i})" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">${i}</button>`;
                    }
                }
                if (lastPage > 5) {
                    buttonsHtml += '<span class="px-2 py-2 text-gray-500">...</span>';
                    buttonsHtml += `<button onclick="goToPage(${lastPage})" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">${lastPage}</button>`;
                }
                buttonsHtml += '</div>';
                
                // Next button
                if (currentPage === lastPage) {
                    buttonsHtml += '<button disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg cursor-not-allowed">Next</button>';
                } else {
                    buttonsHtml += `<button onclick="goToPage(${currentPage + 1})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Next</button>`;
                }
                
                paginationButtons.innerHTML = buttonsHtml;
            }
        }
    }

    async function fetchLogs(page = null) {
        try {
            if (page !== null) currentPage = page;
            
            const startDate = document.getElementById('filter-start-date')?.value || '';
            const endDate = document.getElementById('filter-end-date')?.value || '';
            const device = getDeviceFilterValue();
            const interval = document.getElementById('filter-interval')?.value || '';
            const sort = getSortFilterValue();
            
            const params = new URLSearchParams();
            if (startDate) params.append('from', startDate);
            if (endDate) params.append('to', endDate);
            if (device) params.append('kebun', device);
            if (interval) params.append('interval', interval);
            if (sort) params.append('sort', sort);
            params.append('page', currentPage);
            
            const url = '/api/logs' + (params.toString() ? '?' + params.toString() : '');
            const res = await fetch(url);
            if (!res.ok) return;
            const json = await res.json();
            render(json);
        } catch (e) {
            // ignore
        }
    }

    async function fetchPowerLogs(page = null) {
        try {
            if (page !== null) currentPowerPage = page;

            const device = getPowerDeviceFilterValue();
            const startDate = document.getElementById('filter-start-date')?.value || '';
            const endDate = document.getElementById('filter-end-date')?.value || '';
            const sort = getSortFilterValue();

            const params = new URLSearchParams();
            params.append('page', String(currentPowerPage));
            params.append('per_page', '25');
            if (device) params.append('device', device);
            if (startDate) params.append('from', startDate);
            if (endDate) params.append('to', endDate);
            if (sort) params.append('sort', sort);

            const res = await fetch('/api/power-logs?' + params.toString());
            if (!res.ok) return;

            const json = await res.json();
            renderPowerRows(json.rows || [], json.pagination || null);
        } catch (_) {
            // ignore
        }
    }

    // Add change listeners to filters
    document.getElementById('filter-start-date')?.addEventListener('change', () => { currentPage = 1; currentPowerPage = 1; updateHistoryExportLink(); updatePowerExportLink(); fetchLogs(); fetchPowerLogs(); });
    document.getElementById('filter-end-date')?.addEventListener('change', () => { currentPage = 1; currentPowerPage = 1; updateHistoryExportLink(); updatePowerExportLink(); fetchLogs(); fetchPowerLogs(); });
    document.getElementById('filter-device')?.addEventListener('change', (event) => { syncDeviceFilters(event.target.value || ''); updateHistoryExportLink(); updatePowerExportLink(); currentPage = 1; currentPowerPage = 1; fetchLogs(); fetchPowerLogs(); });
    document.getElementById('filter-power-device')?.addEventListener('change', (event) => { syncDeviceFilters(event.target.value || ''); updateHistoryExportLink(); updatePowerExportLink(); currentPage = 1; currentPowerPage = 1; fetchLogs(); fetchPowerLogs(); });
    document.getElementById('filter-interval')?.addEventListener('change', () => { currentPage = 1; currentPowerPage = 1; fetchLogs(); fetchPowerLogs(); });
    document.getElementById('filter-sort')?.addEventListener('change', () => { currentPage = 1; currentPowerPage = 1; updateHistoryExportLink(); updatePowerExportLink(); fetchLogs(); fetchPowerLogs(); });

    syncDeviceFilters(getDeviceFilterValue());
    updateHistoryExportLink();
    updatePowerExportLink();

    // Function to go to specific page
    window.goToPage = function(page) {
        fetchLogs(page);
    };

    window.goToPowerPage = function(page) {
        fetchPowerLogs(page);
    };

    // initial fetch and start auto-refresh
    fetchLogs();
    fetchPowerLogs();
    setInterval(fetchLogs, intervalMs);
    setInterval(fetchPowerLogs, intervalMs);
})();
</script>

@endsection
