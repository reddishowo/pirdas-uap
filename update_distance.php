<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

if(isset($_GET['distance'])) {
    $distance = $_GET['distance'];
    
    // Save data to a CSV file with timestamp
    $timestamp = date('Y-m-d H:i:s');
    $data = "$timestamp,$distance\n";
    file_put_contents('sensor_data.csv', $data, FILE_APPEND);
    
    // Return JSON response
    echo json_encode([
        'timestamp' => $timestamp,
        'distance' => $distance
    ]);
}