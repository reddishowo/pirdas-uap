<?php
// File: /UAPFIX/update_status.php
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

if(isset($_GET['offset']) && isset($_GET['buzzer'])) {
    $offset = $_GET['offset'];
    $buzzer = $_GET['buzzer'];
    
    // Save status to a file
    $status = [
        'offset' => $offset == '1' ? 'ON' : 'OFF',
        'buzzer' => $buzzer == '1' ? 'ON' : 'OFF',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents('device_status.json', json_encode($status));
    
    // Return current status
    echo json_encode($status);
    exit();
}

// If we get here, there was an error
http_response_code(400);
echo json_encode(['error' => 'Missing parameters']);
?>