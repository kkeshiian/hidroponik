<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\CalibrationSetting;
use App\Models\PowerLog;
use App\Models\Telemetry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttSubscribe extends Command
{
    private const POWER_INTERVAL_SECONDS = 10;
    private const OFFLINE_TIMEOUT_SECONDS = 15;
    private const SLEEP_STABLE_SECONDS = 600;
    private const SLEEP_UNSTABLE_SECONDS = 60;

    /** @var array<string, array<string, mixed>> */
    private array $powerRuntime = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to EMQX topics and handle incoming telemetry messages.';

    public function handle(): int
    {
        // default to public EMQX broker shown by user (plain TCP)
        $host = env('MQTT_HOST', 'broker.emqx.io');
        $port = (int) env('MQTT_PORT', 1883);
        $clientId = env('MQTT_CLIENT_ID', 'hidroponik-subscriber-' . uniqid());
        $username = env('MQTT_USERNAME', null);
        $password = env('MQTT_PASSWORD', null);

        $this->info("Connecting to MQTT broker {$host}:{$port} as {$clientId}...");

        $connectionSettings = (new ConnectionSettings())
            ->setKeepAliveInterval(60)
            ->setLastWillTopic(null);

        if (is_string($username) && trim($username) !== '') {
            $connectionSettings->setUsername($username);
        }

        if (is_string($password) && trim($password) !== '') {
            $connectionSettings->setPassword($password);
        }

        $mqtt = new MqttClient($host, $port, $clientId);

        try {
            $mqtt->connect($connectionSettings, true);
        } catch (\Throwable $e) {
            $this->error('Could not connect to MQTT broker: ' . $e->getMessage());
            Log::error('MQTT connect error: ' . $e->getMessage());
            return 1;
        }

        $mqtt->subscribe('hidroganik/+/publish', function (string $topic, string $message, bool $retained) {
            $this->processMessage($topic, $message, 0, $retained);
        }, 0);
        $mqtt->subscribe('hidroganik/+/status', function (string $topic, string $message, bool $retained) {
            $this->processMessage($topic, $message, 0, $retained);
        }, 0);
        $this->info('Subscribed to: hidroganik/+/publish and hidroganik/+/status');

        $mqtt->registerLoopEventHandler(function () {
            $this->tickPowerGeneration();
        });

        $this->info('Listening for messages + event-driven power generation (press CTRL+C to quit)...');
        try {
            $mqtt->loop(true);
        } catch (\Throwable $e) {
            $this->error('MQTT loop error: ' . $e->getMessage());
            Log::error('MQTT loop error: ' . $e->getMessage());
        }

        // never reached normally
        $mqtt->disconnect();

        return 0;
    }

    protected function processMessage(string $topic, string $message, int $qos, bool $retained): void
    {
        Log::info('MQTT raw message', ['topic' => $topic, 'payload' => $message, 'qos' => $qos, 'retained' => $retained]);

        $parts = explode('/', $topic);
        $kebun = $parts[1] ?? null;
        $kind = strtolower($parts[2] ?? '');

        if (!$kebun || !in_array($kind, ['publish', 'status'], true)) {
            return;
        }

        if ($kind === 'publish') {
            $this->handlePublishMessage($kebun, $topic, $message, $qos, $retained);
            return;
        }

        $this->handleStatusMessage($kebun, $message);
    }

    protected function handleStatusMessage(string $kebun, string $message): void
    {
        $payload = json_decode($message, true);
        if (!is_array($payload)) {
            $payload = ['status' => trim($message)];
        }

        $rawState = $payload['state'] ?? $payload['status'] ?? '';
        $state = $this->normalizeState((string) $rawState);
        $mode = $this->normalizeMode($payload['mode'] ?? $payload['device_mode'] ?? $payload['current_mode'] ?? null);

        if ($state === null && $mode === null) {
            return;
        }

        $sleepSeconds = $state === 'SLEEPING'
            ? $this->resolveSleepSeconds($payload)
            : null;

        $this->markDeviceSignal($kebun, $state, $mode, $sleepSeconds);
    }

    protected function handlePublishMessage(string $kebun, string $topic, string $message, int $qos, bool $retained): void
    {
        $data = json_decode($message, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Log::warning('MQTT invalid JSON', ['topic' => $topic, 'payload' => $message]);
            return;
        }

        $rawPayload = [
            'topic' => $topic,
            'kebun' => $kebun,
            'qos' => $qos,
            'suhu' => $data['suhu'] ?? null,
            'ph' => $data['ph'] ?? null,
            'tds' => $data['tds'] ?? null,
            'cal_ph_netral' => $data['cal_ph_netral'] ?? null,
            'cal_ph_asam' => $data['cal_ph_asam'] ?? null,
            'cal_tds_k' => $data['cal_tds_k'] ?? null,
            'tds_mentah' => $data['tds_mentah'] ?? null,
            'date' => $data['date'] ?? null,
            'time' => $data['time'] ?? null,
            'raw' => $data,
        ];

        $calibratedPayload = $this->applyCalibration($kebun, $rawPayload);
        $this->saveTelemetry($calibratedPayload);

        Cache::put("telemetry:{$kebun}", $calibratedPayload, now()->addMinutes(60));
        $index = Cache::get('telemetry:index', []);
        if (!in_array($kebun, $index, true)) {
            $index[] = $kebun;
            Cache::put('telemetry:index', $index, now()->addHours(6));
        }

        // Jika perangkat 1 kirim data, buat data kembaran untuk perangkat 2
        // dengan payload sama, kecuali TDS di-offset random ±96..±165.
        $mirrorKebun = $this->resolveMirrorDevice($kebun);
        if ($mirrorKebun !== null) {
            $mirroredRawPayload = $rawPayload;
            $mirroredRawPayload['kebun'] = $mirrorKebun;
            $mirroredRawPayload['tds'] = $this->withMirroredTdsOffset($rawPayload['tds'] ?? null);
            $mirroredRawPayload['tds_mentah'] = $mirroredRawPayload['tds'];

            if (is_array($mirroredRawPayload['raw'] ?? null)) {
                $mirroredRawPayload['raw']['kebun'] = $mirrorKebun;
                $mirroredRawPayload['raw']['tds'] = $mirroredRawPayload['tds'];
                $mirroredRawPayload['raw']['mirrored_from'] = $kebun;
            }

            $mirroredPayload = $this->applyCalibration($mirrorKebun, $mirroredRawPayload);
            $this->saveTelemetry($mirroredPayload);

            Cache::put("telemetry:{$mirrorKebun}", $mirroredPayload, now()->addMinutes(60));
            if (!in_array($mirrorKebun, $index, true)) {
                $index[] = $mirrorKebun;
                Cache::put('telemetry:index', $index, now()->addHours(6));
            }
        }

        $mode = $this->normalizeMode($data['mode'] ?? $data['device_mode'] ?? $data['current_mode'] ?? null);
        $state = $mode === 'CALIBRATION' ? 'CALIBRATION' : 'ACTIVE';
        $this->markDeviceSignal($kebun, $state, $mode, null);
        if ($mirrorKebun !== null) {
            $this->markDeviceSignal($mirrorKebun, $state, $mode, null);
        }
    }

    protected function resolveMirrorDevice(string $kebun): ?string
    {
        $device = strtolower(trim($kebun));

        if (in_array($device, ['kebun-1', 'kebun-a', 'a'], true)) {
            return $device === 'kebun-a' ? 'kebun-b' : 'kebun-2';
        }

        return null;
    }

    protected function withMirroredTdsOffset(mixed $tds): ?float
    {
        if ($tds === null || !is_numeric($tds)) {
            return null;
        }

        $base = (float) $tds;
        $offset = random_int(47, 112);
        $sign = random_int(0, 1) === 0 ? -1 : 1;
        $value = $base + ($sign * $offset);

        return max(0, $value);
    }

    

    protected function markDeviceSignal(string $device, ?string $state, ?string $mode, ?int $sleepSeconds): void
    {
        $now = time();
        $runtime = $this->powerRuntime[$device] ?? [
            'enabled' => false,
            'last_mqtt_at' => 0,
            'last_generated_at' => 0,
            'state' => 'BOOT',
            'mode' => 'AUTO',
            'sleep_until' => null,
            'prev_current' => 95.0,
        ];

        $runtime['enabled'] = true;
        $runtime['last_mqtt_at'] = $now;

        if ($mode !== null) {
            $runtime['mode'] = $mode;
        }

        if ($state !== null) {
            $runtime['state'] = $state;
        }

        if ($runtime['state'] === 'CALIBRATION') {
            $runtime['mode'] = 'CALIBRATION';
        }

        if ($runtime['state'] === 'SLEEPING') {
            $seconds = $sleepSeconds ?: self::SLEEP_UNSTABLE_SECONDS;
            $runtime['sleep_seconds'] = $seconds;
            $runtime['sleep_until'] = $now + $seconds;
            Cache::put("device_sleep_seconds:{$device}", $seconds, now()->addHours(24));
        } else {
            $runtime['sleep_seconds'] = null;
            $runtime['sleep_until'] = null;
        }

        $this->powerRuntime[$device] = $runtime;

        Cache::put("device_runtime:{$device}", [
            'device' => $device,
            'state' => $runtime['state'],
            'mode' => $runtime['mode'],
            'sleep_seconds' => $runtime['sleep_seconds'] ?? null,
            'sleep_until' => $runtime['sleep_until'],
            'updated_at' => now()->toDateTimeString(),
            'power_generation_enabled' => (bool) $runtime['enabled'],
        ], now()->addHours(6));
    }

    protected function tickPowerGeneration(): void
    {
        $this->ensurePrimaryPowerDevices();

        if (empty($this->powerRuntime)) {
            return;
        }

        $now = time();
        $intervalSeconds = $this->getPowerIntervalSeconds();

        foreach ($this->powerRuntime as $device => $runtime) {
            if (empty($runtime['enabled'])) {
                continue;
            }

            $state = strtoupper((string) ($runtime['state'] ?? 'ACTIVE'));
            $mode = strtoupper((string) ($runtime['mode'] ?? 'AUTO'));
            $lastSeen = (int) ($runtime['last_mqtt_at'] ?? 0);
            $sleepUntil = $runtime['sleep_until'] ?? null;
            $isSleepingWindow = $state === 'SLEEPING' && is_int($sleepUntil) && $now <= $sleepUntil;
            $isPrimaryDevice = in_array($device, ['kebun-1', 'kebun-2'], true);

            if (!$isPrimaryDevice && !$isSleepingWindow && ($now - $lastSeen) > self::OFFLINE_TIMEOUT_SECONDS) {
                $runtime['enabled'] = false;
                $this->powerRuntime[$device] = $runtime;
                continue;
            }

            $lastGeneratedAt = (int) ($runtime['last_generated_at'] ?? 0);
            if (($now - $lastGeneratedAt) < $intervalSeconds) {
                continue;
            }

            $current = $this->generateCurrentForRuntime($runtime);
            $runtime['last_generated_at'] = $now;
            $this->powerRuntime[$device] = $runtime;

            try {
                PowerLog::create([
                    'device_name' => $device,
                    'state' => $state,
                    'mode' => $mode,
                    'current_ma' => $current,
                    'timestamp' => now(),
                    'is_estimated' => true,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to save power log: ' . $e->getMessage(), ['device' => $device]);
            }
        }
    }

    protected function ensurePrimaryPowerDevices(): void
    {
        $now = time();
        foreach (['kebun-1', 'kebun-2'] as $device) {
            $runtime = $this->powerRuntime[$device] ?? [
                'enabled' => true,
                'last_mqtt_at' => $now,
                'last_generated_at' => 0,
                'state' => 'ACTIVE',
                'mode' => 'AUTO',
                'sleep_until' => null,
                'prev_current' => 95.0,
            ];

            if (!isset($runtime['enabled'])) {
                $runtime['enabled'] = true;
            }
            if (!isset($runtime['last_mqtt_at'])) {
                $runtime['last_mqtt_at'] = $now;
            }

            $this->powerRuntime[$device] = $runtime;
        }
    }

    protected function getPowerIntervalSeconds(): int
    {
        return self::POWER_INTERVAL_SECONDS;
    }

    protected function generateCurrentForRuntime(array &$runtime): float
    {
        $state = strtoupper((string) ($runtime['state'] ?? 'ACTIVE'));
        $mode = strtoupper((string) ($runtime['mode'] ?? 'AUTO'));
        $prevCurrent = (float) ($runtime['prev_current'] ?? 95.0);

        $target = 100.0;
        $jitter = 0.0;
        $spikeChance = 0.0;

        if ($state === 'SLEEPING') {
            $target = $this->randomFloat(1.5, 3.0);
            $jitter = $this->randomFloat(-0.08, 0.08);
        } elseif ($state === 'BOOT') {
            $target = $this->randomFloat(80, 110);
            $jitter = $this->randomFloat(-8, 8);
            $spikeChance = 0.08;
        } elseif ($mode === 'CALIBRATION' || $state === 'CALIBRATION') {
            $target = $this->randomFloat(90, 130);
            $jitter = $this->randomFloat(-2, 2);
            $spikeChance = 0.01;
        } else {
            $target = $this->randomFloat(80, 240);
            $jitter = $this->randomFloat(-4, 4);
            $spikeChance = 0.05;
        }

        $current = ($prevCurrent * 0.65) + ($target * 0.35) + $jitter;

        if ($spikeChance > 0 && mt_rand() / mt_getrandmax() < $spikeChance) {
            $current += $this->randomFloat(20, 40);
        }

        if ($state === 'SLEEPING') {
            $current = $this->clamp($current, 1.5, 3.0);
        } elseif ($state === 'BOOT') {
            $current = $this->clamp($current, 70, 150);
        } elseif ($mode === 'CALIBRATION' || $state === 'CALIBRATION') {
            $current = $this->clamp($current, 88, 140);
        } else {
            $current = $this->clamp($current, 70, 260);
        }

        $runtime['prev_current'] = $current;
        return round($current, 2);
    }

    protected function normalizeState(?string $raw): ?string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        $map = [
            'boot' => 'BOOT',
            'power_on_or_reset' => 'BOOT',
            'active' => 'ACTIVE',
            'device_connected' => 'ACTIVE',
            'wake_up_from_deep_sleep' => 'ACTIVE',
            'calibration' => 'CALIBRATION',
            'mode_cal' => 'CALIBRATION',
            'sleeping' => 'SLEEPING',
            'going_to_sleep' => 'SLEEPING',
        ];

        return $map[$value] ?? null;
    }

    protected function normalizeMode($raw): ?string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        if (in_array($value, ['auto', 'mode_auto'], true)) {
            return 'AUTO';
        }

        if (in_array($value, ['calibration', 'cal', 'mode_cal'], true)) {
            return 'CALIBRATION';
        }

        return null;
    }

    protected function resolveSleepSeconds(array $payload): int
    {
        $explicit = $payload['sleepSeconds'] ?? $payload['sleep_seconds'] ?? $payload['tSleep'] ?? null;
        if (is_numeric($explicit) && (int) $explicit > 0) {
            return (int) $explicit;
        }

        $stableFlags = [
            $payload['stable'] ?? null,
            $payload['isStable'] ?? null,
            $payload['deep_sleep_stable'] ?? null,
            $payload['sleep_stable'] ?? null,
        ];

        foreach ($stableFlags as $flag) {
            if ($flag === null) {
                continue;
            }

            $bool = filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === true) {
                return self::SLEEP_STABLE_SECONDS;
            }
            if ($bool === false) {
                return self::SLEEP_UNSTABLE_SECONDS;
            }
        }

        return self::SLEEP_UNSTABLE_SECONDS;
    }

    protected function randomFloat(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    protected function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }

    protected function applyCalibration(string $kebun, array $payload): array
    {
        // Get calibration settings from cache or DB
        $cacheKey = "calibration:{$kebun}";
        $calibration = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($kebun) {
            return CalibrationSetting::where('kebun', $kebun)->first();
        });

        if (!$calibration) {
            // No calibration found, return original
            return $payload;
        }

        // Apply TDS calibration: TDS × Multiplier
        if (isset($payload['tds']) && $payload['tds'] !== null) {
            $payload['tds'] = round($payload['tds'] * $calibration->tds_multiplier);
        }

        // Apply Suhu calibration: Suhu + Correction
        if (isset($payload['suhu']) && $payload['suhu'] !== null) {
            $payload['suhu'] = round($payload['suhu'] + $calibration->suhu_correction, 2);
        }

        return $payload;
    }

    protected function saveTelemetry(array $payload): void
    {
        try {
            $recordedAt = now();
            if (!empty($payload['date']) && !empty($payload['time'])) {
                try {
                    $parsedRecordedAt = Carbon::parse($payload['date'] . ' ' . $payload['time']);
                    // Jika jam perangkat melenceng jauh dari server, pakai waktu server.
                    $diffMinutes = abs($parsedRecordedAt->diffInMinutes($recordedAt, false));
                    if ($diffMinutes <= 180) {
                        $recordedAt = $parsedRecordedAt;
                    }
                } catch (\Exception $e) {
                    // Use current time if parsing fails
                }
            }

            $created = Telemetry::create([
                'kebun' => $payload['kebun'] ?? null,
                'ph' => $payload['ph'] ?? null,
                'tds' => $payload['tds'] ?? null,
                'suhu' => $payload['suhu'] ?? null,
                'cal_ph_netral' => $payload['cal_ph_netral'] ?? null,
                'cal_ph_asam' => $payload['cal_ph_asam'] ?? null,
                'cal_tds_k' => $payload['cal_tds_k'] ?? null,
                'tds_mentah' => $payload['tds_mentah'] ?? null,
                'raw_payload' => $payload['raw'] ?? $payload,
                'recorded_at' => $recordedAt,
            ]);
            Log::info('Telemetry saved', ['id' => $created->id ?? null]);
        } catch (\Exception $e) {
            Log::error('Failed to save telemetry to database: ' . $e->getMessage());
            echo "DB save error: " . $e->getMessage() . "\n";
        }
    }
}
