<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

if(isset($_GET['distance'])) {
    $distance = $_GET['distance'];
    
    // Save data to a CSV file with timestamp
    $timestamp = date('Y-m-d H:i:s');
    $data = "$timestamp,$distance\n";
    file_put_contents('sensor_data.csv', $data, FILE_APPEND);
    
    // Send the distance as SSE data
    echo "data: " . $distance . "\n\n";
    flush();
}
?>