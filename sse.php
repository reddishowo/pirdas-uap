<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Fungsi untuk mengirim SSE event
function sendSSE($data) {
    echo "data: " . $data . "\n\n";
    ob_flush();
    flush();
}

// Baca status dari file
$statusFile = 'offset_status.txt';
if (!file_exists($statusFile)) {
    file_put_contents($statusFile, 'offset_off');
}

$status = file_get_contents($statusFile);
sendSSE($status);

// Keep connection alive
while (true) {
    // Check if client is still connected
    if (connection_aborted()) break;

    // Read current status
    $currentStatus = file_get_contents($statusFile);
    if ($currentStatus !== $status) {
        $status = $currentStatus;
        sendSSE($status);
    }

    // Sleep for a while to prevent high CPU usage
    sleep(1);
}