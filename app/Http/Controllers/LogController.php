<?php

namespace App\Http\Controllers;

use App\Models\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    private function latestAllowedRecordedAt()
    {
        $maxFutureMinutes = (int) env('MQTT_MAX_FUTURE_MINUTES', 2);
        return now()->addMinutes($maxFutureMinutes);
    }

    public function index(Request $request)
    {
        try {
            $query = Telemetry::query()
                ->where('recorded_at', '<=', $this->latestAllowedRecordedAt());

            // filters (optional)
            if ($request->filled('kebun')) {
                $query->where('kebun', $request->input('kebun'));
            }
            if ($request->filled('from') && $request->filled('to')) {
                $query->whereBetween('recorded_at', [$request->input('from'), $request->input('to')]);
            }

            $total = (int) $query->count();
            $stats = [
                'total' => $total,
                'avg_ph' => round((float) $query->avg('ph'), 2),
                'avg_tds' => (int) round((float) $query->avg('tds')),
                'avg_temp' => round((float) $query->avg('suhu'), 2),
            ];

            $rows = $query->orderBy('recorded_at', 'desc')->paginate(25);

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
            $query = Telemetry::query()
                ->where('recorded_at', '<=', $this->latestAllowedRecordedAt());
            
            // Apply filters
            if ($request->filled('kebun')) {
                $query->where('kebun', $request->input('kebun'));
            }
            if ($request->filled('from')) {
                $query->where('recorded_at', '>=', $request->input('from') . ' 00:00:00');
            }
            if ($request->filled('to')) {
                $query->where('recorded_at', '<=', $request->input('to') . ' 23:59:59');
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
            $rows = $query->orderBy('recorded_at', 'desc')->skip($skip)->take($perPage)->get();
            
            // Calculate stats on filtered data
            $statsQuery = Telemetry::query()
                ->where('recorded_at', '<=', $this->latestAllowedRecordedAt());
            if ($request->filled('kebun')) {
                $statsQuery->where('kebun', $request->input('kebun'));
            }
            if ($request->filled('from')) {
                $statsQuery->where('recorded_at', '>=', $request->input('from') . ' 00:00:00');
            }
            if ($request->filled('to')) {
                $statsQuery->where('recorded_at', '<=', $request->input('to') . ' 23:59:59');
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

        $callback = function () use ($request) {
            $handle = fopen('php://output', 'w');
            // header
            fputcsv($handle, ['Waktu', 'Perangkat', 'pH', 'TDS', 'Suhu', 'Cal pH Asam', 'Cal pH Netral', 'Cal TDS K']);

            try {
                $query = Telemetry::query()
                    ->where('recorded_at', '<=', $this->latestAllowedRecordedAt())
                    ->orderBy('recorded_at', 'desc');
                $query->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $r) {
                        fputcsv($handle, [
                            $r->recorded_at,
                            $r->kebun,
                            $r->ph,
                            $r->tds,
                            $r->suhu,
                            $r->cal_ph_asam,
                            $r->cal_ph_netral,
                            $r->cal_tds_k,
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
}
