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
        'suhu' => 27.50,
        'ph' => 9.19,
        'tds' => 2.57,
        'phVolt' => 2.0790,
        'tdsVolt' => 0.0063,
    ];
    
    $topic = 'hidroganik/kebun-a/publish';
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
