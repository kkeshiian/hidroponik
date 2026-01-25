<?php

require __DIR__ . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

$broker = 'broker.emqx.io';
$port = 1883;
$clientId = 'hidroponik-test-publisher-' . uniqid();

$mqtt = new MqttClient($broker, $port, $clientId);

$connectionSettings = (new ConnectionSettings)
    ->setKeepAliveInterval(60);

try {
    echo "Connecting to MQTT broker...\n";
    $mqtt->connect($connectionSettings);
    
    $testData = [
        'suhu_air' => 28.5,
        'ph' => 6.8,
        'tds' => 650,
        'cal_ph_netral' => 7.0,
        'cal_ph_asam' => 4.0,
        'cal_tds_k' => 1.0,
        'tds_mentah' => 650,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s')
    ];
    
    $topic = 'hidroganik/kebun-a/telemetry';
    $message = json_encode($testData);
    
    echo "Publishing to topic: $topic\n";
    echo "Message: $message\n";
    
    $mqtt->publish($topic, $message, 0);
    
    echo "Message published successfully!\n";
    
    $mqtt->disconnect();
    echo "Disconnected.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
