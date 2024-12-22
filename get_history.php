<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = [];
if (file_exists('sensor_data.csv')) {
    $file = fopen('sensor_data.csv', 'r');
    while (($line = fgetcsv($file)) !== FALSE) {
        $data[] = [
            'timestamp' => $line[0],
            'distance' => floatval($line[1])
        ];
    }
    fclose($file);
}

// Only return the last 50 entries
$data = array_slice($data, -50);
echo json_encode($data);
?>