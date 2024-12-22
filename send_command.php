<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $command = $_POST['command'];
    // Here you would typically validate and sanitize the input
    
    // Log the command (for debugging purposes)
    error_log("Received command: " . $command);

    // In a real-world scenario, you'd want to implement proper security measures
    // For now, we'll just echo back a success message
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>