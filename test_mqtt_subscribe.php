<?php

require __DIR__ . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

$broker = 'broker.emqx.io';
$port = 1883;
$clientId = 'hidroponik-test-sub-' . uniqid();

$mqtt = new MqttClient($broker, $port, $clientId);

$connectionSettings = (new ConnectionSettings)
    ->setKeepAliveInterval(60);

try {
    echo "Connecting to MQTT broker...\n";
    $mqtt->connect($connectionSettings);
    
    echo "Subscribing to topic: hidroganik/+/publish\n";
    $mqtt->subscribe('hidroganik/+/publish', function ($topic, $message) {
        echo "\n[RECEIVED] Topic: $topic\n";
        echo "Message: $message\n\n";
    }, 0);
    
    echo "Listening for messages (press CTRL+C to quit)...\n";
    $mqtt->loop(true);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
