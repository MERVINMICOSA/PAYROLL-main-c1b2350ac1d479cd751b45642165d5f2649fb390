<?php
// api/auth/session.php — Check active session status

require_once __DIR__ . '/../core/bootstrap.php';

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

