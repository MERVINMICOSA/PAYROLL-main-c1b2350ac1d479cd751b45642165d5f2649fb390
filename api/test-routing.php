<?php
// api/test.php - Test endpoint to verify routing works
require_once __DIR__ . '/core/bootstrap.php';
header('Content-Type: application/json');

header('Access-Control-Allow-Credentials: true');

// Check if session is active
$sessionActive = isset($_SESSION['user_id']) && isset($_SESSION['user']);

echo json_encode([
    'status' => 'ok',
    'message' => 'API routing is working',
    'session_active' => $sessionActive,
    'user' => $sessionActive ? $_SESSION['user']['username'] : null,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'time' => date('Y-m-d H:i:s')
]);
?>
