<?php
// api/settings.php - System settings endpoint (superadmin only)

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

// Only superadmin and accountant can manage settings
requireRole(['superadmin', 'accountant']);

require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $key = $_GET['key'] ?? null;
            
            if ($key) {
                // Get single setting
                $setting = $db->fetchOne(
                    "SELECT * FROM settings WHERE key = ?",
                    [Sanitizer::sanitize($key)]
                );
                
                if (!$setting) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Setting not found']);
                    break;
                }
                echo json_encode($setting);
            } else {
                // Get all settings
                $settings = $db->fetchAll("SELECT * FROM settings");
                echo json_encode($settings ?: []);
            }
            break;
        
        case 'POST':
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (!isset($data['key']) || !isset($data['value'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: key, value']);
                break;
            }
            
            $db->insert('settings', [
                'key' => Sanitizer::sanitize($data['key']),
                'value' => Sanitizer::sanitize($data['value']),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode(['success' => true]);
            break;
        
        case 'PUT':
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (!isset($data['key'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Setting key required']);
                break;
            }
            
            $db->update(
                'settings',
                ['value' => Sanitizer::sanitize($data['value'] ?? '')],
                'key = ?',
                [Sanitizer::sanitize($data['key'])]
            );
            
            echo json_encode(['success' => true]);
            break;
        
        case 'DELETE':
            $key = $_GET['key'] ?? null;
            if (!$key) {
                http_response_code(400);
                echo json_encode(['error' => 'Setting key required']);
                break;
            }
            
            $db->delete('settings', 'key = ?', [Sanitizer::sanitize($key)]);
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
