<?php
// api/auth/validate.php — Session validation endpoint

require_once __DIR__ . '/../config/cors-headers.php';
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/session-start.php';

try {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user'])) {
        echo json_encode([
            'valid' => true,
            'user' => $_SESSION['user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'Session expired or invalid'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

