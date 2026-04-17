<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
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
    private const PPM_INTERVAL_SECONDS = 5;
    private const PPM_START_HOUR_WITA = 21;
    private const PPM_REFILL_HOUR_WITA = 6;
    private const PPM_TIMEZONE = 'Asia/Makassar';
    private const PPM_START_MIN = 897.0;
    private const PPM_START_MAX = 905.0;
    private const PPM_TARGET_MIN = 405.0;
    private const PPM_TARGET_MAX = 435.0;
    private const PPM_DURATION_DAYS_MIN = 2.5;
    private const PPM_DURATION_DAYS_MAX = 2.5;
    private const OFFLINE_TIMEOUT_SECONDS = 15;
    private const SLEEP_STABLE_SECONDS = 600;
    private const SLEEP_UNSTABLE_SECONDS = 60;
    private const TELEMETRY_MAX_WRITES_PER_SECOND = 2;
    private const TELEMETRY_WRITE_COOLDOWN_SECONDS = 5;

    /** @var array<string, array<string, mixed>> */
    private array $powerRuntime = [];

    /** @var array<string, array<string, mixed>> */
    private array $ppmRuntime = [];

    private ?int $telemetryWriteSecond = null;

    /** @var array<string, bool> */
    private array $telemetryWrittenDevices = [];

    private ?int $telemetryCooldownUntil = null;

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
            'mode' => $data['mode'] ?? null,
            'device_mode' => $data['device_mode'] ?? null,
            'current_mode' => $data['current_mode'] ?? null,
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

        $rawPayload = $this->applySimulatedPpmIfNeeded($kebun, $rawPayload);

        $calibratedPayload = $this->applyCalibration($kebun, $rawPayload);
        $savedPrimary = $this->saveTelemetry($calibratedPayload);

        $index = Cache::get('telemetry:index', []);
        if ($savedPrimary) {
            Cache::put("telemetry:{$kebun}", $calibratedPayload, now()->addMinutes(60));
            if (!in_array($kebun, $index, true)) {
                $index[] = $kebun;
                Cache::put('telemetry:index', $index, now()->addHours(6));
            }
        }

        // Jika perangkat 1 kirim data, buat data kembaran untuk perangkat 2
        // dengan payload sama, kecuali TDS di-offset random ±96..±165.
        $mirrorKebun = $this->resolveMirrorDevice($kebun);
        if ($mirrorKebun !== null) {
            $mirroredRawPayload = $rawPayload;
            $mirroredRawPayload['kebun'] = $mirrorKebun;
            $mirroredRawPayload['ph'] = $this->withMirroredValueOffset($rawPayload['ph'] ?? null, 1.2, 3.2, 0.0, 14.0, 2);
            $mirroredRawPayload['suhu'] = $this->withMirroredValueOffset($rawPayload['suhu'] ?? null, 1.2, 3.2, 0.0, null, 2);
            $mirroredRawPayload['tds'] = $this->withMirroredTdsOffset($rawPayload['tds'] ?? null);
            $mirroredRawPayload['tds_mentah'] = $mirroredRawPayload['tds'];

            if (is_array($mirroredRawPayload['raw'] ?? null)) {
                $mirroredRawPayload['raw']['kebun'] = $mirrorKebun;
                $mirroredRawPayload['raw']['mode'] = $mirroredRawPayload['mode'] ?? ($rawPayload['mode'] ?? null);
                $mirroredRawPayload['raw']['device_mode'] = $mirroredRawPayload['device_mode'] ?? ($rawPayload['device_mode'] ?? null);
                $mirroredRawPayload['raw']['current_mode'] = $mirroredRawPayload['current_mode'] ?? ($rawPayload['current_mode'] ?? null);
                $mirroredRawPayload['raw']['ph'] = $mirroredRawPayload['ph'];
                $mirroredRawPayload['raw']['suhu'] = $mirroredRawPayload['suhu'];
                $mirroredRawPayload['raw']['tds'] = $mirroredRawPayload['tds'];
                $mirroredRawPayload['raw']['mirrored_from'] = $kebun;
            }

            $mirroredPayload = $this->applyCalibration($mirrorKebun, $mirroredRawPayload);
            $savedMirror = $this->saveTelemetry($mirroredPayload);

            if ($savedMirror) {
                Cache::put("telemetry:{$mirrorKebun}", $mirroredPayload, now()->addMinutes(60));
                if (!in_array($mirrorKebun, $index, true)) {
                    $index[] = $mirrorKebun;
                    Cache::put('telemetry:index', $index, now()->addHours(6));
                }
            }
        }

        $mode = $this->normalizeMode($data['mode'] ?? $data['device_mode'] ?? $data['current_mode'] ?? null);
        $state = $mode === 'CALIBRATION' ? 'CALIBRATION' : 'ACTIVE';
        $this->markDeviceSignal($kebun, $state, $mode, null);
        if ($mirrorKebun !== null) {
            $this->markDeviceSignal($mirrorKebun, $state, $mode, null);
        }
    }

    protected function applySimulatedPpmIfNeeded(string $kebun, array $payload): array
    {
        if (!$this->isAlat1Device($kebun)) {
            return $payload;
        }

        $nowWita = CarbonImmutable::now(self::PPM_TIMEZONE);
        $bucketNow = $nowWita->setSecond((int) (floor($nowWita->second / self::PPM_INTERVAL_SECONDS) * self::PPM_INTERVAL_SECONDS));

        $runtimeKey = 'alat-1';

        $runtime = $this->ppmRuntime[$runtimeKey] ?? null;
        if (!is_array($runtime)) {
            $runtime = $this->buildNewPpmRuntime($bucketNow);
        }

        /** @var CarbonImmutable $lastBucket */
        $lastBucket = $runtime['last_bucket'] instanceof CarbonImmutable
            ? $runtime['last_bucket']
            : CarbonImmutable::parse((string) $runtime['last_bucket'], self::PPM_TIMEZONE);

        /** @var CarbonImmutable $cycleStartedAt */
        $cycleStartedAt = $runtime['cycle_started_at'] instanceof CarbonImmutable
            ? $runtime['cycle_started_at']
            : CarbonImmutable::parse((string) $runtime['cycle_started_at'], self::PPM_TIMEZONE);

        /** @var CarbonImmutable $refillAt */
        $refillAt = $runtime['refill_at'] instanceof CarbonImmutable
            ? $runtime['refill_at']
            : CarbonImmutable::parse((string) $runtime['refill_at'], self::PPM_TIMEZONE);

        $durationSeconds = max(1, (int) ($runtime['duration_seconds'] ?? (int) (self::PPM_DURATION_DAYS_MIN * 86400)));
        $cycleEnd = $cycleStartedAt->addSeconds($durationSeconds);

        if ($bucketNow->lessThanOrEqualTo($lastBucket)) {
            $simulated = (float) ($runtime['value'] ?? $runtime['start']);
            if ($bucketNow->lessThan($cycleEnd)) {
                $simulated = $this->applyIncomingSpikeStep($simulated, $runtime, $bucketNow->hour);
            } else {
                $simulated = $this->simulateLowPpmValue($simulated, $runtime);
            }
            $runtime['value'] = round($simulated);
            $payload['tds'] = (int) round($runtime['value']);
            $payload['tds_mentah'] = (int) round($runtime['value']);
            if (is_array($payload['raw'] ?? null)) {
                $payload['raw']['tds'] = $payload['tds'];
            }
            $this->ppmRuntime[$runtimeKey] = $runtime;
            return $payload;
        }

        $current = (float) ($runtime['value'] ?? $runtime['start']);
        $cursor = $lastBucket;

        // Refill hanya saat pagi, setelah fase turun selesai.
        if ($bucketNow->greaterThanOrEqualTo($refillAt)) {
            $runtime = $this->buildNewPpmRuntime($refillAt);
            $current = (float) ($runtime['value'] ?? $runtime['start']);
            $cursor = $refillAt;
            $durationSeconds = max(1, (int) ($runtime['duration_seconds'] ?? (int) (self::PPM_DURATION_DAYS_MIN * 86400)));
            $cycleStartedAt = $runtime['cycle_started_at'] instanceof CarbonImmutable
                ? $runtime['cycle_started_at']
                : CarbonImmutable::parse((string) $runtime['cycle_started_at'], self::PPM_TIMEZONE);
            $cycleEnd = $cycleStartedAt->addSeconds($durationSeconds);
        }

        while ($cursor->lessThan($bucketNow)) {
            $next = $cursor->addSeconds(self::PPM_INTERVAL_SECONDS);
            if ($next->lessThan($cycleEnd)) {
                $current = $this->nextSimulatedPpmValue($runtime, $current, $next);
            } else {
                $current = $this->simulateLowPpmValue($current, $runtime);
            }
            $cursor = $next;
        }

        if ($bucketNow->lessThan($cycleEnd)) {
            $current = $this->applyIncomingSpikeStep($current, $runtime, $bucketNow->hour);
        } else {
            $current = $this->simulateLowPpmValue($current, $runtime);
        }
        $runtime['value'] = round($current);
        $runtime['last_bucket'] = $bucketNow;
        $this->ppmRuntime[$runtimeKey] = $runtime;

        $payload['tds'] = (int) round((float) $runtime['value']);
        $payload['tds_mentah'] = (int) round((float) $runtime['value']);
        if (is_array($payload['raw'] ?? null)) {
            $payload['raw']['tds'] = $payload['tds'];
            $payload['raw']['tds_mentah'] = $payload['tds_mentah'];
            $payload['raw']['ppm_generated'] = true;
            $payload['raw']['ppm_generated_at_wita'] = $bucketNow->format('H:i:s');
        }

        $this->line($bucketNow->format('H:i:s') . ' ' . (int) $payload['tds']);

        return $payload;
    }

    protected function buildNewPpmRuntime(CarbonImmutable $startedAtWita): array
    {
        $durationSeconds = (int) round(self::PPM_DURATION_DAYS_MIN * 86400);
        $startPpm = round($this->randomFloat(self::PPM_START_MIN, self::PPM_START_MAX));
        $targetPpm = round($this->randomFloat(self::PPM_TARGET_MIN, self::PPM_TARGET_MAX));
        $cycleEnd = $startedAtWita->addSeconds($durationSeconds);

        return [
            'cycle_started_at' => $startedAtWita,
            'start' => $startPpm,
            'target' => $targetPpm,
            'duration_seconds' => $durationSeconds,
            'value' => $startPpm,
            'last_bucket' => $startedAtWita,
            'refill_at' => $this->resolveNextRefillAtWita($cycleEnd),
        ];
    }

    protected function resolveNextRefillAtWita(CarbonImmutable $after): CarbonImmutable
    {
        $candidate = $after->setHour(self::PPM_REFILL_HOUR_WITA)->setMinute(0)->setSecond(0);
        if ($candidate->lessThanOrEqualTo($after)) {
            $candidate = $candidate->addDay();
        }

        return $candidate;
    }

    protected function simulateLowPpmValue(float $current, array $runtime): float
    {
        $target = (float) ($runtime['target'] ?? self::PPM_TARGET_MIN);
        $lowMin = max(380.0, $target - 20.0);
        $lowMax = $target + 10.0;

        $next = ($current * 0.7) + ($target * 0.3) + $this->randomFloat(-0.25, 0.25);
        return $this->clamp($next, $lowMin, $lowMax);
    }

    protected function nextSimulatedPpmValue(array $runtime, float $current, CarbonImmutable $atWita): float
    {
        $start = (float) ($runtime['start'] ?? self::PPM_START_MIN);
        $target = (float) ($runtime['target'] ?? self::PPM_TARGET_MAX);
        $durationSeconds = max(1, (int) ($runtime['duration_seconds'] ?? (int) (self::PPM_DURATION_DAYS_MIN * 86400)));
        $cycleStart = CarbonImmutable::parse((string) ($runtime['cycle_key'] ?? $atWita->format('Y-m-d') . ' 21:00:00'), self::PPM_TIMEZONE);
        $cycleEnd = $cycleStart->addSeconds($durationSeconds);

        $progressNow = $this->weightedProgress($cycleStart, $cycleEnd, $atWita);
        $ideal = $start - (($start - $target) * $progressNow);
        $gap = $current - $ideal;

        [$pDrop, $pRise] = $this->stepProbabilitiesByHour($atWita->hour);

        if ($gap > 3.0) {
            $pDrop = min(0.30, $pDrop + 0.07);
            $pRise = max(0.005, $pRise - 0.01);
        } elseif ($gap < -2.5) {
            $pDrop = max(0.01, $pDrop - 0.04);
            $pRise = min(0.08, $pRise + 0.02);
        }

        $roll = mt_rand() / mt_getrandmax();
        $next = $current;

        if ($roll < $pRise) {
            $rise = $this->smallRiseStep($atWita->hour);
            $next = $current + $rise;
        } elseif ($roll < ($pRise + $pDrop)) {
            $drop = $this->smallDropStep($atWita->hour);
            $next = $current - $drop;
        } else {
            $micro = $this->randomFloat(-0.04, 0.04);
            $next = $current + $micro;
        }

        $noise = $this->randomFloat(-0.03, 0.03);
        $next += $noise;

        $maxAllowed = max($target + 30.0, $start + 5.0);
        $next = $this->clamp($next, max(0.0, $target - 8.0), $maxAllowed);

        return round($next);
    }

    protected function resolveCycleStartWita(CarbonImmutable $atWita): CarbonImmutable
    {
        $todayStart = $atWita->setHour(self::PPM_START_HOUR_WITA)->setMinute(0)->setSecond(0);

        if ($atWita->greaterThanOrEqualTo($todayStart)) {
            return $todayStart;
        }

        return $todayStart->subDay();
    }

    protected function weightedProgress(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $at): float
    {
        if ($at->lessThanOrEqualTo($start)) {
            return 0.0;
        }
        if ($at->greaterThanOrEqualTo($end)) {
            return 1.0;
        }

        $weightedElapsed = $this->weightedSecondsBetween($start, $at);
        $weightedTotal = max(1.0, $this->weightedSecondsBetween($start, $end));

        return $this->clamp($weightedElapsed / $weightedTotal, 0.0, 1.0);
    }

    protected function weightedSecondsBetween(CarbonImmutable $from, CarbonImmutable $to): float
    {
        $cursor = $from;
        $weighted = 0.0;

        while ($cursor->lessThan($to)) {
            $nextHour = $cursor->addHour()->startOfHour();
            if ($nextHour->lessThanOrEqualTo($cursor)) {
                $nextHour = $cursor->addHour();
            }

            $segmentEnd = $nextHour->lessThan($to) ? $nextHour : $to;
            $seconds = max(0, $cursor->diffInSeconds($segmentEnd, false));
            $weighted += $seconds * $this->hourWeight($cursor->hour);
            $cursor = $segmentEnd;
        }

        return $weighted;
    }

    protected function hourWeight(int $hour): float
    {
        if ($hour >= 9 && $hour < 18) {
            return 1.65;
        }

        if ($hour >= 18 || $hour < 6) {
            return 0.65;
        }

        return 1.0;
    }

    protected function stepProbabilitiesByHour(int $hour): array
    {
        if ($hour >= 9 && $hour < 18) {
            return [0.11, 0.02];
        }

        if ($hour >= 18 || $hour < 6) {
            return [0.045, 0.015];
        }

        return [0.07, 0.018];
    }

    protected function smallDropStep(int $hour): float
    {
        $base = 0.1;
        $boostChance = ($hour >= 9 && $hour < 18) ? 0.06 : 0.02;

        if ((mt_rand() / mt_getrandmax()) < $boostChance) {
            return round($base + $this->randomFloat(0.1, 0.7), 1);
        }

        return round($base + $this->randomFloat(0.0, 0.12), 1);
    }

    protected function smallRiseStep(int $hour): float
    {
        $maxRise = ($hour >= 9 && $hour < 18) ? 0.4 : 0.3;

        return round(0.1 + $this->randomFloat(0.0, $maxRise - 0.1), 1);
    }

    protected function applyIncomingSpikeStep(float $current, array $runtime, int $hour): float
    {
        $target = (float) ($runtime['target'] ?? self::PPM_TARGET_MAX);
        $start = (float) ($runtime['start'] ?? self::PPM_START_MIN);
        $ideal = $target + (($start - $target) * 0.2);
        $gap = $current - $ideal;

        $magnitude = round($this->randomFloat(2.0, 6.0));
        $downBias = $gap > 1.5 ? 0.7 : 0.5;
        if ($hour >= 9 && $hour < 18) {
            $downBias = min(0.8, $downBias + 0.08);
        }
        $sign = (mt_rand() / mt_getrandmax()) < $downBias ? -1.0 : 1.0;

        $spiked = $current + ($sign * $magnitude);
        $maxAllowed = max($target + 30.0, $start + 5.0);

        return $this->clamp($spiked, max(0.0, $target - 8.0), $maxAllowed);
    }

    protected function isAlat1Device(string $kebun): bool
    {
        $device = strtolower(trim($kebun));
        return in_array($device, ['kebun-1', 'kebun-a', 'a'], true);
    }

    protected function resolveMirrorDevice(string $kebun): ?string
    {
        $device = strtolower(trim($kebun));

        if (in_array($device, ['kebun-1', 'kebun-a', 'a'], true)) {
            return 'kebun-2';
        }

        return null;
    }

    protected function withMirroredTdsOffset(mixed $tds): ?float
    {
        if ($tds === null || !is_numeric($tds)) {
            return null;
        }

        $base = (float) $tds;
        $offset = random_int(56, 96);
        $sign = random_int(0, 1) === 0 ? -1 : 1;
        $value = $base + ($sign * $offset);

        return max(0, $value);
    }

    protected function withMirroredValueOffset(
        mixed $value,
        float $minOffset,
        float $maxOffset,
        ?float $minAllowed = null,
        ?float $maxAllowed = null,
        int $precision = 2
    ): ?float {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        $base = (float) $value;
        $offset = $this->randomFloat($minOffset, $maxOffset);
        $sign = random_int(0, 1) === 0 ? -1 : 1;
        $result = $base + ($sign * $offset);

        if ($minAllowed !== null) {
            $result = max($minAllowed, $result);
        }
        if ($maxAllowed !== null) {
            $result = min($maxAllowed, $result);
        }

        return round($result, $precision);
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
            $payload['tds'] = (int) round($payload['tds'] * $calibration->tds_multiplier);
        }

        // Apply Suhu calibration: Suhu + Correction
        if (isset($payload['suhu']) && $payload['suhu'] !== null) {
            $payload['suhu'] = round($payload['suhu'] + $calibration->suhu_correction, 2);
        }

        return $payload;
    }

    protected function normalizeTelemetryDevice(?string $kebun): ?string
    {
        $value = strtolower(trim((string) $kebun));

        if (in_array($value, ['kebun-1', 'kebun-a', 'a'], true)) {
            return 'kebun-1';
        }

        if (in_array($value, ['kebun-2', 'kebun-b', 'b'], true)) {
            return 'kebun-2';
        }

        return null;
    }

    protected function canWriteTelemetryNow(?string $kebun): bool
    {
        $canonicalDevice = $this->normalizeTelemetryDevice($kebun);
        if ($canonicalDevice === null) {
            Log::info('Telemetry skipped by limiter: unsupported device', ['kebun' => $kebun]);
            return false;
        }

        $nowTs = time();
        if ($this->telemetryCooldownUntil !== null && $nowTs < $this->telemetryCooldownUntil) {
            Log::info('Telemetry skipped by limiter: cooldown active', [
                'kebun' => $kebun,
                'cooldown_until' => date('Y-m-d H:i:s', $this->telemetryCooldownUntil),
            ]);
            return false;
        }

        if ($this->telemetryWriteSecond !== $nowTs) {
            $this->telemetryWriteSecond = $nowTs;
            $this->telemetryWrittenDevices = [];
        }

        if (isset($this->telemetryWrittenDevices[$canonicalDevice])) {
            Log::info('Telemetry skipped by limiter: device already written in this second', ['kebun' => $kebun]);
            return false;
        }

        if (count($this->telemetryWrittenDevices) >= self::TELEMETRY_MAX_WRITES_PER_SECOND) {
            Log::info('Telemetry skipped by limiter: max writes per second reached', ['kebun' => $kebun]);
            return false;
        }

        return true;
    }

    protected function markTelemetryWritten(?string $kebun): void
    {
        $canonicalDevice = $this->normalizeTelemetryDevice($kebun);
        if ($canonicalDevice === null) {
            return;
        }

        $nowTs = time();
        if ($this->telemetryWriteSecond !== $nowTs) {
            $this->telemetryWriteSecond = $nowTs;
            $this->telemetryWrittenDevices = [];
        }

        $this->telemetryWrittenDevices[$canonicalDevice] = true;

        if (count($this->telemetryWrittenDevices) >= self::TELEMETRY_MAX_WRITES_PER_SECOND) {
            $this->telemetryCooldownUntil = $nowTs + self::TELEMETRY_WRITE_COOLDOWN_SECONDS;
        }
    }

    protected function saveTelemetry(array $payload): bool
    {
        try {
            $kebun = $payload['kebun'] ?? null;
            if (!$this->canWriteTelemetryNow($kebun)) {
                return false;
            }

            $serverNow = now();
            $recordedAt = $serverNow->copy();
            $maxFutureMinutes = (int) env('MQTT_MAX_FUTURE_MINUTES', 2);

            if (!empty($payload['date']) && !empty($payload['time'])) {
                try {
                    $parsedRecordedAt = Carbon::parse(
                        $payload['date'] . ' ' . $payload['time'],
                        config('app.timezone')
                    );

                    if ($parsedRecordedAt->greaterThan($serverNow->copy()->addMinutes($maxFutureMinutes))) {
                        Log::warning('Telemetry skipped: future device timestamp detected', [
                            'kebun' => $payload['kebun'] ?? null,
                            'device_time' => $parsedRecordedAt->toDateTimeString(),
                            'server_time' => $serverNow->toDateTimeString(),
                            'tds' => $payload['tds'] ?? null,
                        ]);
                        return false;
                    }

                    // Jika jam perangkat melenceng jauh dari server, pakai waktu server.
                    $diffMinutes = abs($parsedRecordedAt->diffInMinutes($serverNow, false));
                    if ($diffMinutes <= 180) {
                        $recordedAt = $parsedRecordedAt;
                    }
                } catch (\Exception $e) {
                    // Use current time if parsing fails
                }
            }

            // Normalize to second precision so duplicate payloads in the same second are ignored.
            $recordedAt = Carbon::parse($recordedAt->format('Y-m-d H:i:s'));

            $created = Telemetry::firstOrCreate(
                [
                    'kebun' => $kebun,
                    'recorded_at' => $recordedAt,
                ],
                [
                    'ph' => $payload['ph'] ?? null,
                    'tds' => $payload['tds'] ?? null,
                    'suhu' => $payload['suhu'] ?? null,
                    'cal_ph_netral' => $payload['cal_ph_netral'] ?? null,
                    'cal_ph_asam' => $payload['cal_ph_asam'] ?? null,
                    'cal_tds_k' => $payload['cal_tds_k'] ?? null,
                    'tds_mentah' => $payload['tds_mentah'] ?? null,
                    'raw_payload' => $payload['raw'] ?? $payload,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $this->markTelemetryWritten($kebun);
                Log::info('Telemetry saved', ['id' => $created->id ?? null]);
                return true;
            }

            Log::info('Telemetry duplicate skipped', [
                'kebun' => $kebun,
                'recorded_at' => $recordedAt->toDateTimeString(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to save telemetry to database: ' . $e->getMessage());
            echo "DB save error: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
