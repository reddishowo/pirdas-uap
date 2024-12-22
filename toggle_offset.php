<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents('php://input'), true);
$status = $data['status'] ?? 'off';

// Save status to file
file_put_contents('offset_status.txt', 'offset_' . $status);

// Send response
echo json_encode(['success' => true, 'status' => $status]);