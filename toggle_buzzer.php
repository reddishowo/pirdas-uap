<?php
header('Content-Type: application/json');

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json);

if ($data && isset($data->status)) {
    $status = 'buzzer_' . $data->status;
    
    // Write to the same status file used by SSE
    $statusFile = 'offset_status.txt';
    file_put_contents($statusFile, $status);
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>