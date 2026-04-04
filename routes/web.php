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
use App\Models\PowerLog;
use App\Models\Telemetry;

Route::get('/log', [LogController::class, 'index'])->name('log');
Route::get('/log/export', [LogController::class, 'export'])->name('log.export');
Route::get('/log/power-export', [LogController::class, 'powerExport'])->name('log.power.export');
Route::get('/api/logs', [LogController::class, 'api'])->name('api.logs');
Route::get('/api/power-logs', [LogController::class, 'powerApi'])->name('api.power.logs');
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

Route::get('/api/device-runtime-state', function () {
    $aliases = [
        // Prioritize physical device aliases first to avoid stale UI-key cache overriding live data.
        'kebun-a' => ['kebun-1', 'kebun-a', 'a'],
        'kebun-b' => ['kebun-2', 'kebun-b', 'b'],
    ];

    // If ACTIVE data is stale, treat it as sleeping so UI does not show a false awake state
    // after page navigation/reload when no fresh MQTT signal has arrived yet.
    $staleActiveSeconds = 120;
    $shortSleepSeconds = 60;
    $longSleepSeconds = 600;

    $result = [];

    $extractSleepSecondsFromPayload = static function ($payload): ?int {
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        if (!is_array($payload)) {
            return null;
        }

        $candidates = [
            $payload['sleepSeconds'] ?? null,
            $payload['sleep_seconds'] ?? null,
            $payload['tSleep'] ?? null,
        ];

        if (isset($payload['raw']) && is_array($payload['raw'])) {
            $candidates[] = $payload['raw']['sleepSeconds'] ?? null;
            $candidates[] = $payload['raw']['sleep_seconds'] ?? null;
            $candidates[] = $payload['raw']['tSleep'] ?? null;
        }

        foreach ($candidates as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    };

    foreach ($aliases as $uiKey => $deviceAliases) {
        $runtime = null;
        $lastKnownSleepSeconds = null;
        $telemetrySleepSeconds = null;
        $runtimeCandidates = [];
        $aliasPriority = array_flip($deviceAliases);

        foreach ($deviceAliases as $alias) {
            $savedSleep = Cache::get("device_sleep_seconds:{$alias}");
            if (is_numeric($savedSleep) && (int) $savedSleep > 0) {
                $lastKnownSleepSeconds = (int) $savedSleep;
                break;
            }
        }

        $latestTelemetry = Telemetry::query()
            ->whereIn('kebun', $deviceAliases)
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at')
            ->first();
        if ($latestTelemetry) {
            $telemetrySleepSeconds = $extractSleepSecondsFromPayload($latestTelemetry->raw_payload);
        }

        foreach ($deviceAliases as $alias) {
            $cached = Cache::get("device_runtime:{$alias}");
            if (!empty($cached) && is_array($cached)) {
                $cachedDevice = strtolower((string) ($cached['device'] ?? $alias));
                if (!array_key_exists($cachedDevice, $aliasPriority)) {
                    continue;
                }
                $cached['_alias_index'] = $aliasPriority[$cachedDevice];
                $runtimeCandidates[] = $cached;
            }
        }

        if (!empty($runtimeCandidates)) {
            usort($runtimeCandidates, static function (array $a, array $b): int {
                $aTs = isset($a['updated_at']) ? strtotime((string) $a['updated_at']) : false;
                $bTs = isset($b['updated_at']) ? strtotime((string) $b['updated_at']) : false;
                $aVal = $aTs !== false ? $aTs : 0;
                $bVal = $bTs !== false ? $bTs : 0;
                if ($bVal !== $aVal) {
                    return $bVal <=> $aVal;
                }

                $aIndex = isset($a['_alias_index']) ? (int) $a['_alias_index'] : PHP_INT_MAX;
                $bIndex = isset($b['_alias_index']) ? (int) $b['_alias_index'] : PHP_INT_MAX;
                return $aIndex <=> $bIndex;
            });
            $runtime = $runtimeCandidates[0];
            unset($runtime['_alias_index']);
        }

        if (!$runtime) {
            $latestPower = PowerLog::query()
                ->whereIn('device_name', $deviceAliases)
                ->orderByDesc('timestamp')
                ->first();

            if ($latestPower) {
                $runtime = [
                    'device' => $latestPower->device_name,
                    'state' => strtoupper((string) $latestPower->state),
                    'mode' => strtoupper((string) $latestPower->mode),
                    'sleep_seconds' => $lastKnownSleepSeconds,
                    'sleep_until' => null,
                    'updated_at' => optional($latestPower->timestamp)?->toDateTimeString(),
                    'power_generation_enabled' => true,
                ];
            }
        }

        if (!empty($runtime['state']) && !empty($runtime['updated_at'])) {
            $state = strtoupper((string) $runtime['state']);
            $updatedAt = strtotime((string) $runtime['updated_at']);
            $ageSeconds = $updatedAt !== false ? max(0, time() - $updatedAt) : 0;
            $sleepSeconds = (int) ($runtime['sleep_seconds'] ?? 0);
            if ($sleepSeconds <= 0 && $lastKnownSleepSeconds !== null) {
                $sleepSeconds = $lastKnownSleepSeconds;
                $runtime['sleep_seconds'] = $sleepSeconds;
            }
            if ($sleepSeconds <= 0 && $telemetrySleepSeconds !== null) {
                $sleepSeconds = $telemetrySleepSeconds;
                $runtime['sleep_seconds'] = $sleepSeconds;
            }
            $sleepUntil = isset($runtime['sleep_until']) ? (int) $runtime['sleep_until'] : 0;

            if ($state === 'ACTIVE' && $updatedAt !== false) {
                if ($ageSeconds > $staleActiveSeconds) {
                    $runtime['state'] = 'SLEEPING';
                    if ($sleepSeconds <= 0) {
                        $sleepSeconds = $ageSeconds >= $longSleepSeconds ? $longSleepSeconds : $shortSleepSeconds;
                        $runtime['sleep_seconds'] = $sleepSeconds;
                    }
                    if ($sleepUntil <= 0) {
                        $runtime['sleep_until'] = $updatedAt + $sleepSeconds;
                    }
                }
            }

            if (strtoupper((string) $runtime['state']) === 'SLEEPING') {
                if ($sleepSeconds <= 0 && $sleepUntil > 0 && $updatedAt !== false) {
                    $inferred = $sleepUntil - $updatedAt;
                    if ($inferred > 0) {
                        $sleepSeconds = $inferred;
                        $runtime['sleep_seconds'] = $sleepSeconds;
                    }
                }

                if ($sleepSeconds <= 0) {
                    $sleepSeconds = $ageSeconds >= $longSleepSeconds ? $longSleepSeconds : $shortSleepSeconds;
                    $runtime['sleep_seconds'] = $sleepSeconds;
                }

                if (($sleepUntil <= 0) && $updatedAt !== false) {
                    $runtime['sleep_until'] = $updatedAt + $sleepSeconds;
                }
            }
        }

        $result[$uiKey] = $runtime;
    }

    return Response::json($result);
});
