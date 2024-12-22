<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents('php://input'), true);
$status = $data['status'] ?? 'off';

// Simpan status ke file
file_put_contents('offset_status.txt', 'offset_' . $status);

echo json_encode(['success' => true, 'status' => $status]);