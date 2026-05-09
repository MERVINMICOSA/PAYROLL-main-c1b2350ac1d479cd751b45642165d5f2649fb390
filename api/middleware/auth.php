<?php
// api/middleware/auth.php — Session-based authentication helpers

require_once __DIR__ . '/../config/cors-headers.php';
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/../config/session-start.php';

function validateSession() {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in', 'session_active' => false]);
        exit;
    }
    return $_SESSION['user'];
}

function getCurrentUser() {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        return null;
    }
    return $_SESSION['user'];
}

/**
 * Require specific role(s) for endpoint access.
 * Returns 401 if not logged in, 403 if role not allowed.
 * 
 * @param array $allowedRoles List of allowed roles (e.g., ['superadmin', 'accountant'])
 * @return array User data if authorized
 */
function requireRole(array $allowedRoles) {
    $user = getCurrentUser();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in', 'session_active' => false]);
        exit;
    }
    
    $userRole = strtolower($user['role'] ?? '');
    $allowedRolesLower = array_map('strtolower', $allowedRoles);
    
    if (!in_array($userRole, $allowedRolesLower)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Forbidden - Insufficient privileges',
            'required_roles' => $allowedRoles,
            'your_role' => $user['role'] ?? 'unknown'
        ]);
        exit;
    }
    
    return $user;
}
