<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('home');
});

Route::get('/home', function () {
    return view('home');
})->name('home');

use App\Http\Controllers\LogController;
use App\Http\Controllers\CalibrationController;
use App\Http\Controllers\SettingsController;
use App\Models\Telemetry;

Route::get('/log', [LogController::class, 'index'])->name('log');
Route::get('/log/export', [LogController::class, 'export'])->name('log.export');
Route::get('/api/logs', [LogController::class, 'api'])->name('api.logs');
Route::get('/api/telemetry/history', [LogController::class, 'history'])->name('api.telemetry.history');

Route::get('/kalibrasi', [CalibrationController::class, 'index'])->name('kalibrasi');
Route::post('/kalibrasi/{kebun}', [CalibrationController::class, 'update'])->name('kalibrasi.update');
Route::post('/kalibrasi/{kebun}/test', [CalibrationController::class, 'test'])->name('kalibrasi.test');

Route::get('/pengaturan', [SettingsController::class, 'index'])->name('pengaturan');
Route::post('/pengaturan/interval', [SettingsController::class, 'updateInterval'])->name('pengaturan.interval');
Route::post('/pengaturan/delete', [SettingsController::class, 'deleteData'])->name('pengaturan.delete');

// API: latest telemetry (polled by frontend)
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

Route::get('/api/telemetry/latest', function () {
    $normalizeKebun = static function (?string $kebun): ?string {
        if (!$kebun) {
            return null;
        }

        $k = strtolower(trim($kebun));
        $alias = [
            'kebun-1' => 'kebun-a',
            'kebun-a' => 'kebun-a',
            'a' => 'kebun-a',
            'kebun-2' => 'kebun-b',
            'kebun-b' => 'kebun-b',
            'b' => 'kebun-b',
        ];

        return $alias[$k] ?? $k;
    };

    $index = Cache::get('telemetry:index', []);
    $result = [];

    foreach ($index as $kebun) {
        $normalized = $normalizeKebun($kebun);
        if (!$normalized) {
            continue;
        }

        $cached = Cache::get("telemetry:{$kebun}", null);
        if ($cached !== null && !isset($cached['suhu']) && isset($cached['suhu_air'])) {
            $cached['suhu'] = $cached['suhu_air'];
        }

        $result[$normalized] = $cached;
    }

    // Fallback to database when cache is empty or key is missing.
    foreach (['kebun-a', 'kebun-b'] as $kebunKey) {
        if (!empty($result[$kebunKey])) {
            continue;
        }

        $dbAliases = $kebunKey === 'kebun-a'
            ? ['kebun-a', 'kebun-1', 'a']
            : ['kebun-b', 'kebun-2', 'b'];

        $latest = Telemetry::whereIn('kebun', $dbAliases)
            ->orderByDesc('recorded_at')
            ->first();

        if ($latest) {
            $payload = $latest->toArray();
            $payload['kebun'] = $kebunKey;
            if (!isset($payload['suhu']) && isset($payload['suhu_air'])) {
                $payload['suhu'] = $payload['suhu_air'];
            }
            $result[$kebunKey] = $payload;
        }
    }

    return Response::json($result);
});
