<?php
// api/auth/session.php — Check active session status

require_once __DIR__ . '/../config/cors-headers.php';
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Prevent HTML error output
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/session-start.php';

try {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => $_SESSION['user'],
            'session_id' => session_id()
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'authenticated' => false,
            'message' => 'No active session'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Session check failed: ' . $e->getMessage()
    ]);
}

