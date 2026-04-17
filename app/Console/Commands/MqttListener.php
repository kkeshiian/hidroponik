<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\Telemetry;
use App\Models\CalibrationSetting;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MqttListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to MQTT broker and save telemetry data to database';

    private $lastSaveTime = null;
    private $saveInterval = 0; // dalam detik, 0 = realtime

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('===========================================');
        $this->info('  MQTT Listener Service - Hidroponik System');
        $this->info('===========================================');
        $this->info('Starting MQTT Listener...');
        
        // Konfigurasi MQTT dari environment atau default
        $broker   = env('MQTT_BROKER', 'broker.emqx.io');
        $port     = (int) env('MQTT_PORT', 1883);
        $clientId = 'hidroponik_listener_' . uniqid();
        $username = env('MQTT_USERNAME', null);
        $password = env('MQTT_PASSWORD', null);
        $topic    = env('MQTT_TOPIC', 'hidroganik/+/publish');

        $this->info("MQTT Broker: $broker:$port");
        $this->info("MQTT Topic: $topic");
        $this->info("Client ID: $clientId");
        
        // Load interval setting dari database
        $this->loadSaveInterval();

        // Setup connection settings
        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setLastWillTopic('hidroponik/listener/status')
            ->setLastWillMessage('offline')
            ->setLastWillQualityOfService(1);

        if ($username && $password) {
            $connectionSettings
                ->setUsername($username)
                ->setPassword($password);
        }

        $mqtt = new MqttClient($broker, $port, $clientId);

        try {
            $mqtt->connect($connectionSettings, true);
            $this->info('✓ Connected to MQTT broker successfully');

            // Publish status online
            $mqtt->publish('hidroponik/listener/status', 'online', 1, true);

            // Subscribe to publish topic
            $mqtt->subscribe($topic, function ($topic, $message) {
                $this->handleMessage($topic, $message);
            }, 0);

            $this->info("✓ Subscribed to topic: $topic");
            $this->info('');
            $this->info('Listening for messages... (Press CTRL+C to stop)');
            $this->info('-------------------------------------------');

            // Loop forever
            $mqtt->loop(true);

        } catch (\Exception $e) {
            $this->error('✗ MQTT Error: ' . $e->getMessage());
            Log::error('MQTT Listener Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Handle incoming MQTT message
     */
    private function handleMessage($topic, $message)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("[$timestamp] Received from: $topic");
        
        try {
            // Parse JSON message
            $data = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('✗ Invalid JSON: ' . json_last_error_msg());
                Log::error('Invalid JSON received', ['message' => $message, 'topic' => $topic]);
                return;
            }

            $this->line("Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));

            // Cek apakah sudah waktunya menyimpan berdasarkan interval
            if ($this->shouldSaveData()) {
                $this->saveToDatabase($data, $topic);
                $this->lastSaveTime = Carbon::now();
            } else {
                $nextSave = $this->lastSaveTime ? 
                    $this->lastSaveTime->addSeconds($this->saveInterval)->diffForHumans() : 
                    'soon';
                $this->comment("⏳ Data diterima tapi belum disimpan. Simpan berikutnya: $nextSave");
            }

        } catch (\Exception $e) {
            $this->error('✗ Error processing message: ' . $e->getMessage());
            Log::error('Error processing MQTT message', [
                'error' => $e->getMessage(),
                'message' => $message,
                'topic' => $topic,
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->line('-------------------------------------------');
    }

    /**
     * Save data to database
     */
    private function saveToDatabase($data, $topic)
    {
        try {
            // Extract kebun dari topic (hidroganik/[kebun]/publish)
            $topicParts = explode('/', $topic);
            $kebun = $topicParts[1] ?? 'unknown';

            $recordedAt = Carbon::now();
            if (!empty($data['date']) && !empty($data['time'])) {
                try {
                    $parsedRecordedAt = Carbon::parse($data['date'] . ' ' . $data['time']);
                    $diffMinutes = abs($parsedRecordedAt->diffInMinutes($recordedAt, false));
                    if ($diffMinutes <= 180) {
                        $recordedAt = $parsedRecordedAt;
                    }
                } catch (\Throwable $e) {
                    // Keep server time if payload timestamp cannot be parsed.
                }
            }
            $recordedAt = Carbon::parse($recordedAt->format('Y-m-d H:i:s'));

            // Get calibration settings
            $calibration = CalibrationSetting::latest()->first();

            $telemetry = Telemetry::firstOrCreate(
                [
                    'kebun' => $kebun,
                    'recorded_at' => $recordedAt,
                ],
                [
                    'ph' => $data['ph'] ?? null,
                    'tds' => $data['tds'] ?? null,
                    'suhu' => $data['suhu'] ?? $data['temperature'] ?? null,
                    'cal_ph_netral' => $calibration->ph_netral ?? null,
                    'cal_ph_asam' => $calibration->ph_asam ?? null,
                    'cal_tds_k' => $calibration->tds_k ?? null,
                    'tds_mentah' => $data['tds_mentah'] ?? $data['tds_raw'] ?? null,
                    'raw_payload' => $data,
                ]
            );

            if ($telemetry->wasRecentlyCreated) {
                $this->info("✓ Data saved to database (ID: {$telemetry->id})");
                Log::info('Telemetry data saved', [
                    'id' => $telemetry->id,
                    'kebun' => $kebun,
                    'data' => $data,
                ]);
            } else {
                $this->comment("⏭ Duplicate skipped for {$kebun} at {$recordedAt->toDateTimeString()}");
                Log::info('Telemetry duplicate skipped', [
                    'kebun' => $kebun,
                    'recorded_at' => $recordedAt->toDateTimeString(),
                ]);
            }

            $normalizedKebun = strtolower(trim((string) $kebun));
            if (in_array($normalizedKebun, ['kebun-1', 'kebun-a', 'a'], true)) {
                $mirroredRaw = $data;
                $mirroredRaw['kebun'] = 'kebun-2';
                $mirroredRaw['mirrored_from'] = $kebun;

                $mirror = Telemetry::firstOrCreate(
                    [
                        'kebun' => 'kebun-2',
                        'recorded_at' => $recordedAt,
                    ],
                    [
                        'ph' => $data['ph'] ?? null,
                        'tds' => $data['tds'] ?? null,
                        'suhu' => $data['suhu'] ?? $data['temperature'] ?? null,
                        'cal_ph_netral' => $calibration->ph_netral ?? null,
                        'cal_ph_asam' => $calibration->ph_asam ?? null,
                        'cal_tds_k' => $calibration->tds_k ?? null,
                        'tds_mentah' => $data['tds_mentah'] ?? $data['tds_raw'] ?? null,
                        'raw_payload' => $mirroredRaw,
                    ]
                );

                if ($mirror->wasRecentlyCreated) {
                    $this->info("✓ Mirror saved to kebun-2 (ID: {$mirror->id})");
                    Log::info('Telemetry mirror saved', [
                        'id' => $mirror->id,
                        'kebun' => 'kebun-2',
                        'source' => $kebun,
                    ]);
                }
            }

        } catch (\Exception $e) {
            $this->error('✗ Error saving to database: ' . $e->getMessage());
            Log::error('Database save error', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Load save interval from database or configuration
     */
    private function loadSaveInterval()
    {
        // Cek di database atau config untuk interval setting
        // Misalnya bisa dari tabel settings atau config file
        // Untuk sekarang, gunakan env atau default realtime
        
        $intervalMinutes = (int) env('MQTT_SAVE_INTERVAL_MINUTES', 0);
        $this->saveInterval = $intervalMinutes * 60; // convert to seconds
        
        if ($this->saveInterval == 0) {
            $this->info('Save Interval: Realtime (setiap data langsung disimpan)');
        } else {
            $this->info("Save Interval: {$intervalMinutes} menit");
        }
    }

    /**
     * Check if data should be saved based on interval
     */
    private function shouldSaveData()
    {
        // Jika realtime (interval = 0), selalu simpan
        if ($this->saveInterval == 0) {
            return true;
        }

        // Jika belum pernah simpan, simpan sekarang
        if ($this->lastSaveTime === null) {
            return true;
        }

        // Cek apakah sudah lewat interval
        $secondsSinceLastSave = Carbon::now()->diffInSeconds($this->lastSaveTime);
        return $secondsSinceLastSave >= $this->saveInterval;
    }
}
