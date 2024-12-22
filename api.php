<?php
// Set headers at the beginning of the file
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start output buffering to capture any errors
ob_start();

require_once 'mqtt_handler.php';

$handler = new MQTTHandler();
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'get_readings':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $readings = $handler->getLatestReadings($limit);
            $response = ['success' => true, 'data' => $readings];
            break;
        case 'get_status':
            $status = $handler->getLatestStatus();
            if ($status === false || $status === null) {
                $response = [
                    'success' => true,
                    'data' => [
                        'offset_status' => false,
                        'buzzer_status' => false,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                $status['offset_status'] = (bool)$status['offset_status'];
                $status['buzzer_status'] = (bool)$status['buzzer_status'];
                $response = ['success' => true, 'data' => $status];
            }
            break;
        case 'send_command':
            $command = $_POST['command'] ?? '';
            if (empty($command)) {
                $response = ['success' => false, 'message' => 'No command specified'];
                break;
            }
            if ($handler->publishCommand($command)) {
                $response = ['success' => true, 'message' => 'Command sent successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to send command'];
            }
            break;
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

// Get any buffered output (which would include errors or warnings)
$errorOutput = ob_get_clean();

// If there was any output, include it in the response
if (!empty($errorOutput)) {
    $response['errors'] = $errorOutput;
}

// Output the JSON response
echo json_encode($response);