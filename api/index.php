<?php
// api/index.php - Test endpoint
header("Content-Type: application/json");

echo json_encode([
    'status' => 'online',
    'message' => 'Payroll System API is running',
    'version' => '1.0.0'
]);
?>