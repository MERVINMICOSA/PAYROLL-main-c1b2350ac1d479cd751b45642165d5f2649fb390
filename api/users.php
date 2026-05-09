<?php
// api/users.php - User management endpoint (superadmin only)

require_once __DIR__ . '/config/session-start.php';
require_once __DIR__ . '/config/cors-headers.php';
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/middleware/sanitize.php';
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Prevent any HTML output on errors
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $errstr]);
    exit;
});

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Validate session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please log in', 'session_active' => false]);
    exit;
}

// Only superadmin can manage users
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireRole(['superadmin']);
}

require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            
            if ($id) {
                // Get single user
                $user = $db->getUserById($id);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    break;
                }
                // Remove sensitive data
                unset($user['password_hash']);
                echo json_encode($user);
            } else {
                // Get all users
                $users = $db->getAllUsers();
                // Remove sensitive data from all users
                foreach ($users as &$user) {
                    unset($user['password_hash']);
                }
                echo json_encode($users ?: []);
            }
            break;
        
        case 'POST':
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (!isset($data['username']) || !isset($data['password_hash']) || !isset($data['full_name']) || !isset($data['role'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: username, password_hash, full_name, role']);
                break;
            }
            
            $id = $db->addUser($data);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                break;
            }
            
            // Don't allow updating password via this endpoint
            unset($data['password_hash']);
            
            $db->updateUser((int)$data['id'], $data);
            echo json_encode(['success' => true]);
            break;
        
        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                break;
            }
            $db->deleteUser((int)$id);
            echo json_encode(['success' => true]);
            break;
        
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
