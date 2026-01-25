<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\CalibrationSetting;
use App\Models\Telemetry;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttSubscribe extends Command
{
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
            ->setUsername($username)
            ->setPassword($password)
            ->setKeepAliveInterval(60)
            ->setLastWillTopic(null);

        $mqtt = new MqttClient($host, $port, $clientId);

        try {
            $mqtt->connect($connectionSettings, true);
        } catch (\Throwable $e) {
            $this->error('Could not connect to MQTT broker: ' . $e->getMessage());
            Log::error('MQTT connect error: ' . $e->getMessage());
            return 1;
        }

        $topicFilter = 'hidroganik/+/telemetry';
        $this->info("Subscribing to topic filter: {$topicFilter}");

        $mqtt->subscribe($topicFilter, function (string $topic, string $message, int $qualityOfService, bool $retained) {
            echo "[" . date('Y-m-d H:i:s') . "] MQTT msg on: {$topic}\n";
            echo "Payload: {$message}\n";
            $this->processMessage($topic, $message, $qualityOfService, $retained);
        }, 0);

        // Loop forever to keep subscription alive
        $this->info('Listening for messages (press CTRL+C to quit)...');
        try {
            while (true) {
                // blocking loop for up to 1 second waiting for messages
                $mqtt->loop(true);
            }
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
        $this->info("Message received on {$topic} (QoS={$qos})");
        Log::info('MQTT raw message', ['topic' => $topic, 'payload' => $message, 'qos' => $qos, 'retained' => $retained]);

        // Try to decode JSON
        $data = json_decode($message, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Received invalid JSON: ' . json_last_error_msg());
            Log::warning('MQTT invalid JSON', ['topic' => $topic, 'payload' => $message]);
            return;
        }

        // extract kebun name from topic (expected format: hidroganik/{kebun}/telemetry)
        $parts = explode('/', $topic);
        $kebun = $parts[1] ?? null;

        // normalize expected fields (example from your format)
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

        // Apply calibration
        $calibratedPayload = $this->applyCalibration($kebun, $rawPayload);

        // Log calibrated payload
        Log::info('MQTT telemetry received (calibrated)', $calibratedPayload);
        echo "Calibrated TDS: " . ($calibratedPayload['tds'] ?? 'null') . " | Suhu: " . ($calibratedPayload['suhu'] ?? 'null') . "\n";

        // Store to database
        $this->saveTelemetry($calibratedPayload);
        echo "Saved to DB (kebun=" . ($calibratedPayload['kebun'] ?? '-') . ")\n";

        // store latest telemetry in cache for frontend polling
        if (!empty($kebun)) {
            $key = "telemetry:{$kebun}";
            Cache::put($key, $calibratedPayload, now()->addMinutes(60));

            // maintain an index of kebuns
            $index = Cache::get('telemetry:index', []);
            if (!in_array($kebun, $index)) {
                $index[] = $kebun;
                Cache::put('telemetry:index', $index, now()->addHours(6));
            }
        }
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
                    $recordedAt = \Carbon\Carbon::parse($payload['date'] . ' ' . $payload['time']);
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
                'recorded_at' => $recordedAt,
            ]);
            Log::info('Telemetry saved', ['id' => $created->id ?? null]);
        } catch (\Exception $e) {
            Log::error('Failed to save telemetry to database: ' . $e->getMessage());
            echo "DB save error: " . $e->getMessage() . "\n";
        }
    }
}
