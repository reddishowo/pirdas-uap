<?php
// sse.php

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Include necessary files
require_once 'mqtt_handler.php';

// Create an instance of MQTTHandler
$handler = new MQTTHandler();

// Function to send data to the client
function sendData($handler) {
    // Get the latest readings
    $readings = $handler->getLatestReadings(50);
    
    // Log the number of readings fetched
    error_log("Number of readings fetched: " . count($readings));

    // Prepare the data to be sent
    echo "data: " . json_encode($readings) . "\n\n";
    ob_flush();
    flush();
}

// Main loop for sending updates
while (true) {
    try {
        sendData($handler);
        // Sleep for 2 seconds before sending the next update
        sleep(2);
    } catch (Exception $e) {
        // Log the error and continue
        error_log("SSE Error: " . $e->getMessage());
    }
}