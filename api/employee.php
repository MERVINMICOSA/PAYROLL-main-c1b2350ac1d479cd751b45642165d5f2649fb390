<?php
// api/employee.php - Get single employee endpoint
session_start();

// Allow both localhost and production origin
$allowed_origins = array(
    'http://localhost:5500',
    'http://localhost:3000',
    'http://localhost:8000',
    'http://127.0.0.1:5500',
    'https://philtech-payroll.onrender.com'
);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

// Prevent any HTML output on errors
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $errstr]);
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
    echo json_encode(['error' => 'Unauthorized - Please log in']);
    exit;
}

require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Employee ID required']);
            exit;
        }
        
        $employee = $db->getEmployeeById($id);
        
        if (!$employee) {
            http_response_code(404);
            echo json_encode(['error' => 'Employee not found']);
            exit;
        }
        
        echo json_encode($employee);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $t->getMessage()]);
}
?>