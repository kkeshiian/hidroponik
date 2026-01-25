<?php

namespace App\Http\Controllers;

use App\Models\CalibrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CalibrationController extends Controller
{
    public function index()
    {
        $settings = CalibrationSetting::all()->keyBy('kebun');
        return view('kalibrasi', compact('settings'));
    }

    public function update(Request $request, $kebun)
    {
        // Accept both JSON and form-encoded; relax requirements to allow partial updates
        $validated = $request->validate([
            'tds_multiplier' => 'nullable|numeric|min:0',
            'suhu_correction' => 'nullable|numeric',
        ]);

        $setting = CalibrationSetting::where('kebun', $kebun)->first();
        if (!$setting) {
            $setting = CalibrationSetting::create([
                'kebun' => $kebun,
                'tds_multiplier' => $validated['tds_multiplier'] ?? 1.0,
                'suhu_correction' => $validated['suhu_correction'] ?? 0.0,
            ]);
        } else {
            // Only update provided fields
            $setting->update(array_filter([
                'tds_multiplier' => $validated['tds_multiplier'] ?? null,
                'suhu_correction' => $validated['suhu_correction'] ?? null,
            ], function ($v) { return $v !== null; }));
        }

        // Clear cache to force reload calibration settings
        Cache::forget("calibration:{$kebun}");

        return response()->json([
            'success' => true,
            'message' => 'Kalibrasi berhasil diperbarui',
            'data' => $setting
        ], 200, ['Content-Type' => 'application/json']);
    }

    public function test(Request $request, $kebun)
    {
        $validated = $request->validate([
            'tds_raw' => 'nullable|numeric',
            'suhu_raw' => 'nullable|numeric',
            'tds_multiplier' => 'required|numeric',
            'suhu_correction' => 'required|numeric',
        ]);

        $result = [];

        if (isset($validated['tds_raw'])) {
            $result['tds_calibrated'] = round($validated['tds_raw'] * $validated['tds_multiplier']);
            $result['tds_raw'] = $validated['tds_raw'];
        }

        if (isset($validated['suhu_raw'])) {
            $result['suhu_calibrated'] = round($validated['suhu_raw'] + $validated['suhu_correction'], 2);
            $result['suhu_raw'] = $validated['suhu_raw'];
        }

        return response()->json($result, 200, ['Content-Type' => 'application/json']);
    }
}
