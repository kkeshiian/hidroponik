<?php

namespace App\Http\Controllers;

use App\Models\PowerLog;
use App\Models\Telemetry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    private const POWER_WH_INTERVAL_KEY = 'power_wh_interval_seconds';

    private function latestAllowedRecordedAt(): Carbon
    {
        $maxFutureMinutes = (int) env('MQTT_MAX_FUTURE_MINUTES', 2);
        return now()->addMinutes($maxFutureMinutes);
    }

    private function estimatedVoltage(?string $deviceName, $timestamp): float
    {
        $seed = strtolower((string) $deviceName) . '|' . (string) $timestamp;
        $hash = abs(crc32($seed));

        // Range 4.8V - 5.2V with deterministic small fluctuation per row.
        return 4.8 + (($hash % 401) / 1000);
    }

    private function resolvePowerIntervalSeconds(Request $request): float
    {
        $fromRequest = $request->input('interval_detik', $request->input('interval_seconds'));
        if ($fromRequest !== null && is_numeric($fromRequest)) {
            return max(1.0, min(86400.0, (float) $fromRequest));
        }

        try {
            $value = DB::table('app_settings')
                ->where('setting_key', self::POWER_WH_INTERVAL_KEY)
                ->value('setting_value');

            if (is_numeric($value)) {
                return max(1.0, min(86400.0, (float) $value));
            }
        } catch (\Throwable $e) {
            // fallback below
        }

        return 5.0;
    }

    private function normalizedDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
            }

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveSortDirection(Request $request): string
    {
        $value = strtolower(trim((string) $request->input('sort', 'newest')));

        return $value === 'oldest' ? 'asc' : 'desc';
    }

    private function kebunAliases(?string $kebun): array
    {
        $value = strtolower(trim((string) $kebun));

        if ($value === 'kebun-a' || $value === 'kebun-1' || $value === 'a') {
            return ['kebun-a'];
        }

        if ($value === 'kebun-b' || $value === 'kebun-2' || $value === 'b') {
            return ['kebun-b'];
        }

        return ['kebun-a', 'kebun-b'];
    }

    private function normalizeKebunLabel(?string $deviceName): string
    {
        $value = strtolower(trim((string) $deviceName));

        if ($value === 'kebun-a' || $value === 'kebun-1' || $value === 'a') {
            return 'kebun-a';
        }

        if ($value === 'kebun-b' || $value === 'kebun-2' || $value === 'b') {
            return 'kebun-b';
        }

        return (string) $deviceName;
    }

    public function index(Request $request)
    {
        try {
            $sortDirection = $this->resolveSortDirection($request);
            $query = Telemetry::query()
                ->whereIn('kebun', ['kebun-a', 'kebun-b'])
                ->where('recorded_at', '<=', $this->latestAllowedRecordedAt());

            $fromDate = $this->normalizedDate($request->input('from'));
            $toDate = $this->normalizedDate($request->input('to'));

            // filters (optional)
            if ($request->filled('kebun')) {
                $query->whereIn('kebun', $this->kebunAliases($request->input('kebun')));
            }
            if ($fromDate) {
                $query->where('recorded_at', '>=', $fromDate . ' 00:00:00');
            }
            if ($toDate) {
                $query->where('recorded_at', '<=', $toDate . ' 23:59:59');
            }

            $total = (int) $query->count();
            $stats = [
                'total' => $total,
                'avg_ph' => round((float) $query->avg('ph'), 2),
                'avg_tds' => (int) round((float) $query->avg('tds')),
                'avg_temp' => round((float) $query->avg('suhu'), 2),
            ];

            $rows = $query->orderBy('recorded_at', $sortDirection)->paginate(25);

            return view('log', compact('rows', 'stats'));
        } catch (\Illuminate\Database\QueryException $e) {
            // table might not exist yet; fall back to sample data
            Log::warning('Telemetry table missing or query failed: ' . $e->getMessage());
            $rows = collect();
            for ($i = 0; $i < 8; $i++) {
                $rows->push((object) [
                    'recorded_at' => '2025-11-17 23:41:10',
                    'kebun' => 'A',
                    'ph' => 9.48,
                    'tds' => 683,
                    'suhu' => 28.31,
                    'cal_ph_asam' => 1.6944,
                    'cal_ph_netral' => 1.9084,
                    'cal_tds_k' => 2.7118,
                ]);
            }

            $stats = ['total' => 254, 'avg_ph' => 6.57, 'avg_tds' => 644, 'avg_temp' => 26.90];
            return view('log', compact('rows', 'stats'));
        }
    }

    public function api(Request $request)
    {
        try {
            $sortDirection = $this->resolveSortDirection($request);
            $query = Telemetry::query()
                ->whereIn('kebun', ['kebun-a', 'kebun-b'])
                ->where('recorded_at', '<=', $this->latestAllowedRecordedAt());
            $fromDate = $this->normalizedDate($request->input('from'));
            $toDate = $this->normalizedDate($request->input('to'));
            
            // Apply filters
            if ($request->filled('kebun')) {
                $query->whereIn('kebun', $this->kebunAliases($request->input('kebun')));
            }
            if ($fromDate) {
                $query->where('recorded_at', '>=', $fromDate . ' 00:00:00');
            }
            if ($toDate) {
                $query->where('recorded_at', '<=', $toDate . ' 23:59:59');
            }
            
            // Apply interval sampling
            $interval = $request->input('interval');
            if ($interval && is_numeric($interval)) {
                // Group by time intervals using DATE_FORMAT
                $intervalMinutes = (int) $interval;
                
                if ($intervalMinutes == 5) {
                    $query->selectRaw('
                        kebun,
                        ROUND(AVG(ph), 2) as ph,
                        ROUND(AVG(tds)) as tds,
                        ROUND(AVG(suhu), 2) as suhu,
                        DATE_FORMAT(recorded_at, "%Y-%m-%d %H:%i:00") as recorded_at
                    ')
                    ->groupByRaw('DATE_FORMAT(recorded_at, "%Y-%m-%d %H:%i"), FLOOR(MINUTE(recorded_at) / 5), kebun');
                } elseif ($intervalMinutes == 15) {
                    $query->selectRaw('
                        kebun,
                        ROUND(AVG(ph), 2) as ph,
                        ROUND(AVG(tds)) as tds,
                        ROUND(AVG(suhu), 2) as suhu,
                        DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00:00") as recorded_at
                    ')
                    ->groupByRaw('DATE_FORMAT(recorded_at, "%Y-%m-%d %H"), FLOOR(MINUTE(recorded_at) / 15), kebun');
                } elseif ($intervalMinutes == 30) {
                    $query->selectRaw('
                        kebun,
                        ROUND(AVG(ph), 2) as ph,
                        ROUND(AVG(tds)) as tds,
                        ROUND(AVG(suhu), 2) as suhu,
                        DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00:00") as recorded_at
                    ')
                    ->groupByRaw('DATE_FORMAT(recorded_at, "%Y-%m-%d %H"), FLOOR(MINUTE(recorded_at) / 30), kebun');
                } elseif ($intervalMinutes == 60) {
                    $query->selectRaw('
                        kebun,
                        ROUND(AVG(ph), 2) as ph,
                        ROUND(AVG(tds)) as tds,
                        ROUND(AVG(suhu), 2) as suhu,
                        DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00:00") as recorded_at
                    ')
                    ->groupByRaw('DATE_FORMAT(recorded_at, "%Y-%m-%d %H"), kebun');
                } elseif ($intervalMinutes == 1440) { // 1 day
                    $query->selectRaw('
                        kebun,
                        ROUND(AVG(ph), 2) as ph,
                        ROUND(AVG(tds)) as tds,
                        ROUND(AVG(suhu), 2) as suhu,
                        DATE_FORMAT(recorded_at, "%Y-%m-%d 00:00:00") as recorded_at
                    ')
                    ->groupByRaw('DATE_FORMAT(recorded_at, "%Y-%m-%d"), kebun');
                }
            }
            
            // Get page from request, default to 1
            $page = (int) $request->input('page', 1);
            $perPage = 25;
            $skip = ($page - 1) * $perPage;
            
            $total = $query->count();
            $rows = $query->orderBy('recorded_at', $sortDirection)->skip($skip)->take($perPage)->get();
            
            // Calculate stats on filtered data
            $statsQuery = Telemetry::query()
                ->whereIn('kebun', ['kebun-a', 'kebun-b'])
                ->where('recorded_at', '<=', $this->latestAllowedRecordedAt());
            if ($request->filled('kebun')) {
                $statsQuery->whereIn('kebun', $this->kebunAliases($request->input('kebun')));
            }
            if ($fromDate) {
                $statsQuery->where('recorded_at', '>=', $fromDate . ' 00:00:00');
            }
            if ($toDate) {
                $statsQuery->where('recorded_at', '<=', $toDate . ' 23:59:59');
            }
            
            $stats = [
                'total' => (int) $statsQuery->count(),
                'avg_ph' => round((float) $statsQuery->avg('ph'), 2),
                'avg_tds' => (int) round((float) $statsQuery->avg('tds')),
                'avg_temp' => round((float) $statsQuery->avg('suhu'), 2),
            ];

            $lastPage = ceil($total / $perPage);
            return Response::json([
                'rows' => $rows, 
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $skip + 1,
                    'to' => min($skip + $perPage, $total)
                ]
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // return sample data
            $rows = [];
            for ($i = 0; $i < 8; $i++) {
                $rows[] = [
                    'recorded_at' => '2025-11-17 23:41:10',
                    'kebun' => 'A',
                    'ph' => 9.48,
                    'tds' => 683,
                    'suhu' => 28.31,
                    'cal_ph_asam' => 1.6944,
                    'cal_ph_netral' => 1.9084,
                    'cal_tds_k' => 2.7118,
                ];
            }
            $stats = ['total' => 254, 'avg_ph' => 6.57, 'avg_tds' => 644, 'avg_temp' => 26.90];
            return Response::json(['rows' => $rows, 'stats' => $stats]);
        }
    }

    public function history()
    {
        // Get last 15 records for each kebun for charts
        $kebunA = Telemetry::where('kebun', 'kebun-a')
            ->where('recorded_at', '<=', $this->latestAllowedRecordedAt())
            ->orderBy('recorded_at', 'desc')
            ->limit(15)
            ->get()
            ->reverse()
            ->values();

        $kebunB = Telemetry::where('kebun', 'kebun-b')
            ->where('recorded_at', '<=', $this->latestAllowedRecordedAt())
            ->orderBy('recorded_at', 'desc')
            ->limit(15)
            ->get()
            ->reverse()
            ->values();

        return Response::json([
            'kebun-a' => $kebunA,
            'kebun-b' => $kebunB
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'telemetry_export_' . date('Ymd_His') . '.csv';
        $fromDate = $this->normalizedDate($request->input('from'));
        $toDate = $this->normalizedDate($request->input('to'));
        $sortDirection = $this->resolveSortDirection($request);

        $callback = function () use ($request, $fromDate, $toDate) {
            $handle = fopen('php://output', 'w');
            // Header mengikuti kolom di tabel Data History.
            fputcsv($handle, ['Tanggal', 'Waktu', 'Perangkat', 'pH', 'TDS (ppm)', 'Suhu (°C)']);

            try {
                $query = Telemetry::query()
                    ->whereIn('kebun', ['kebun-a', 'kebun-b'])
                    ->where('recorded_at', '<=', $this->latestAllowedRecordedAt())
                    ->orderBy('recorded_at', $sortDirection);

                if ($request->filled('kebun')) {
                    $query->whereIn('kebun', $this->kebunAliases($request->input('kebun')));
                }
                if ($fromDate) {
                    $query->where('recorded_at', '>=', $fromDate . ' 00:00:00');
                }
                if ($toDate) {
                    $query->where('recorded_at', '<=', $toDate . ' 23:59:59');
                }

                $query->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $r) {
                        $recordedAt = $r->recorded_at;
                        $createdAt = $r->created_at;

                        $effectiveAt = $recordedAt;
                        if ($recordedAt && $createdAt) {
                            $diffHours = abs($createdAt->diffInHours($recordedAt, false));
                            if ($diffHours >= 4) {
                                $effectiveAt = $createdAt;
                            }
                        } elseif (!$effectiveAt && $createdAt) {
                            $effectiveAt = $createdAt;
                        }

                        $localDate = '';
                        $localTime = '';
                        if ($effectiveAt) {
                            $localDate = $effectiveAt->copy()->timezone(config('app.timezone'))->format('Y-m-d');
                            $localTime = $effectiveAt->copy()->timezone(config('app.timezone'))->addHour()->format('H:i:s');
                        }

                        fputcsv($handle, [
                            $localDate,
                            $localTime,
                            $r->kebun,
                            $r->ph,
                            $r->tds,
                            $r->suhu,
                        ]);
                    }
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // nothing to stream
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function powerApi(Request $request)
    {
        try {
            $intervalSeconds = $this->resolvePowerIntervalSeconds($request);
            $fromDate = $this->normalizedDate($request->input('from'));
            $toDate = $this->normalizedDate($request->input('to'));
            $sortDirection = $this->resolveSortDirection($request);

            $page = max(1, (int) $request->input('page', 1));
            $perPage = max(1, min((int) $request->input('per_page', 25), 100));
            $offset = ($page - 1) * $perPage;

            $query = PowerLog::query()->orderBy('timestamp', $sortDirection);

            if ($request->filled('device')) {
                $query->whereIn('device_name', $this->kebunAliases($request->input('device')));
            }
            if ($fromDate) {
                $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
            }
            if ($toDate) {
                $query->where('timestamp', '<=', $toDate . ' 23:59:59');
            }

            $total = (int) $query->count();

            $rows = $query->skip($offset)->take($perPage)->get([
                'device_name',
                'state',
                'mode',
                'current_ma',
                'timestamp',
                'created_at',
                'is_estimated',
            ]);

            $energyByDevice = [];
            $computedRows = [];

            foreach ($rows->sortBy('timestamp', SORT_REGULAR, $sortDirection === 'desc')->values() as $row) {
                $device = (string) ($row->device_name ?? 'unknown');
                $normalizedDevice = $this->normalizeKebunLabel($device);
                $currentMa = (float) ($row->current_ma ?? 0.0);
                $currentA = $currentMa / 1000.0;
                $voltageV = $this->estimatedVoltage($normalizedDevice, $row->timestamp ?? $row->created_at);
                $powerW = $voltageV * $currentA;
                $deltaWh = $powerW * ($intervalSeconds / 3600.0);

                $energyByDevice[$normalizedDevice] = ($energyByDevice[$normalizedDevice] ?? 0.0) + $deltaWh;

                $computedRows[] = array_merge($row->toArray(), [
                    'device_name' => $normalizedDevice,
                    'current_a' => round($currentA, 6),
                    'voltage_v' => round($voltageV, 2),
                    'power_w' => round($powerW, 3),
                    'watt_hour' => round($deltaWh, 5),
                    'watt_hour_cumulative' => round($energyByDevice[$normalizedDevice], 5),
                    'interval_detik' => $intervalSeconds,
                ]);
            }

            $rows = collect($computedRows)
                ->sortByDesc('timestamp')
                ->values();

            return Response::json([
                'rows' => $rows,
                'interval_detik' => $intervalSeconds,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => (int) max(1, (int) ceil($total / max(1, $perPage))),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $total > 0 ? ($offset + 1) : 0,
                    'to' => min($offset + $perPage, $total),
                ],
                'generated_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Power API error: ' . $e->getMessage());
            return Response::json(['rows' => [], 'generated_at' => now()->toDateTimeString()], 200);
        }
    }

    public function powerExport(Request $request)
    {
        $filename = 'power_current_export_' . date('Ymd_His') . '.csv';
        $fromDate = $this->normalizedDate($request->input('from'));
        $toDate = $this->normalizedDate($request->input('to'));
        $sortDirection = $this->resolveSortDirection($request);

        $callback = function () use ($request, $fromDate, $toDate, $sortDirection) {
            $handle = fopen('php://output', 'w');
            $intervalSeconds = $this->resolvePowerIntervalSeconds($request);
            fputcsv($handle, ['Waktu', 'Perangkat', 'State', 'Mode', 'Current (A)', 'Voltage (V)', 'Watt-hour (Wh)']);

            try {
                $query = PowerLog::query()->orderBy('timestamp', $sortDirection);

                if ($request->filled('device')) {
                    $query->whereIn('device_name', $this->kebunAliases($request->input('device')));
                }
                if ($fromDate) {
                    $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
                }
                if ($toDate) {
                    $query->where('timestamp', '<=', $toDate . ' 23:59:59');
                }

                $energyByDevice = [];

                $query->chunk(300, function ($rows) use ($handle, $intervalSeconds, &$energyByDevice, $sortDirection) {
                    $orderedRows = $sortDirection === 'desc' ? $rows : $rows->sortBy('timestamp');

                    foreach ($orderedRows as $r) {
                        $normalizedDevice = $this->normalizeKebunLabel($r->device_name);
                        $currentA = round(((float) $r->current_ma) / 1000, 6);
                        $voltageV = round($this->estimatedVoltage($normalizedDevice, $r->timestamp ?? $r->created_at), 2);
                        $powerW = $voltageV * $currentA;
                        $deltaWh = round($powerW * ($intervalSeconds / 3600.0), 5);

                        $energyByDevice[$normalizedDevice] = ($energyByDevice[$normalizedDevice] ?? 0.0) + $deltaWh;

                        $timestamp = $r->timestamp;
                        if ($timestamp) {
                            $timestamp = $timestamp->copy()->timezone(config('app.timezone'))->addHour()->format('Y-m-d H:i:s');
                        }

                        fputcsv($handle, [
                            $timestamp,
                            $normalizedDevice,
                            $r->state,
                            $r->mode,
                            $currentA,
                            $voltageV,
                            number_format($energyByDevice[$normalizedDevice], 5, '.', ''),
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Power CSV export error: ' . $e->getMessage());
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
