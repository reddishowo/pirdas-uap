<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents('php://input'), true);
$mode = $data['mode'] ?? 'auto';

// Save mode to file
file_put_contents('mode_status.txt', $mode);

// Send SSE event
$status = ($mode == 'manual') ? 'mode_manual' : 'mode_auto';
file_put_contents('offset_status.txt', $status);

echo json_encode(['success' => true, 'mode' => $mode]);