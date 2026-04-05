<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Models\Telemetry;
use Carbon\Carbon;

class SettingsController extends Controller
{
    private $configFile = 'mqtt_save_interval.json';
    private const POWER_WH_INTERVAL_KEY = 'power_wh_interval_seconds';
    
    public function index()
    {
        // Load current interval setting
        $interval = $this->loadInterval();
        $powerWhIntervalSeconds = $this->loadPowerWhIntervalSeconds();
        
        // Get database statistics
        try {
            $stats = [
                'total_records' => Telemetry::count(),
                'oldest_record' => Telemetry::orderBy('recorded_at', 'asc')->first()?->recorded_at,
                'newest_record' => Telemetry::orderBy('recorded_at', 'desc')->first()?->recorded_at,
                'db_size' => $this->getDatabaseSize(),
            ];
        } catch (\Exception $e) {
            $stats = [
                'total_records' => 0,
                'oldest_record' => null,
                'newest_record' => null,
                'db_size' => 'N/A',
            ];
        }
        
        return view('pengaturan', compact('interval', 'powerWhIntervalSeconds', 'stats'));
    }
    
    public function updateInterval(Request $request)
    {
        $validated = $request->validate([
            'interval' => 'required|in:realtime,5,10,15,30,60,720,1440'
        ]);
        
        $this->saveInterval($validated['interval']);
        
        return Response::json([
            'success' => true,
            'message' => 'Interval penyimpanan berhasil diperbarui'
        ]);
    }

    public function updatePowerInterval(Request $request)
    {
        $validated = $request->validate([
            'interval_detik' => 'required|integer|min:1|max:86400',
        ]);

        $this->savePowerWhIntervalSeconds((int) $validated['interval_detik']);

        return Response::json([
            'success' => true,
            'message' => 'Interval Wh berhasil diperbarui',
            'interval_detik' => (int) $validated['interval_detik'],
        ]);
    }
    
    public function deleteData(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'required|in:1week,2weeks,1month,3months,6months,1year,all'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        }
        
        $period = $validated['period'];
        $deleted = 0;
        
        try {
            switch ($period) {
                case '1week':
                    $deleted = Telemetry::where('recorded_at', '<', Carbon::now()->subWeek())->delete();
                    break;
                case '2weeks':
                    $deleted = Telemetry::where('recorded_at', '<', Carbon::now()->subWeeks(2))->delete();
                    break;
                case '1month':
                    $deleted = Telemetry::where('recorded_at', '<', Carbon::now()->subMonth())->delete();
                    break;
                case '3months':
                    $deleted = Telemetry::where('recorded_at', '<', Carbon::now()->subMonths(3))->delete();
                    break;
                case '6months':
                    $deleted = Telemetry::where('recorded_at', '<', Carbon::now()->subMonths(6))->delete();
                    break;
                case '1year':
                    $deleted = Telemetry::where('recorded_at', '<', Carbon::now()->subYear())->delete();
                    break;
                case 'all':
                    $deleted = Telemetry::query()->delete();
                    break;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deleted} data",
                'deleted' => $deleted
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function loadInterval()
    {
        if (Storage::disk('local')->exists($this->configFile)) {
            $json = Storage::disk('local')->get($this->configFile);
            $data = json_decode($json, true);
            return $data['interval'] ?? 'realtime';
        }
        return 'realtime';
    }
    
    private function saveInterval($interval)
    {
        $data = ['interval' => $interval];
        Storage::disk('local')->put($this->configFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function loadPowerWhIntervalSeconds(): int
    {
        try {
            $value = DB::table('app_settings')
                ->where('setting_key', self::POWER_WH_INTERVAL_KEY)
                ->value('setting_value');

            if ($value === null || !is_numeric($value)) {
                return 5;
            }

            return max(1, min(86400, (int) $value));
        } catch (\Throwable $e) {
            return 5;
        }
    }

    private function savePowerWhIntervalSeconds(int $seconds): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['setting_key' => self::POWER_WH_INTERVAL_KEY],
            [
                'setting_value' => (string) $seconds,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
    
    private function getDatabaseSize()
    {
        try {
            $dbName = env('DB_DATABASE');
            $result = DB::selectOne("
                SELECT 
                    SUM(data_length + index_length) as size 
                FROM information_schema.TABLES 
                WHERE table_schema = ? 
                AND table_name = 'telemetries'
            ", [$dbName]);
            
            $bytes = $result->size ?? 0;
            
            if ($bytes >= 1073741824) {
                return number_format($bytes / 1073741824, 2) . ' GB';
            } elseif ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2) . ' MB';
            } elseif ($bytes >= 1024) {
                return number_format($bytes / 1024, 2) . ' KB';
            }
            return $bytes . ' bytes';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
