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
    $index = Cache::get('telemetry:index', []);
    $result = [];
    foreach ($index as $kebun) {
        $result[$kebun] = Cache::get("telemetry:{$kebun}", null);
    }
    return Response::json($result);
});
