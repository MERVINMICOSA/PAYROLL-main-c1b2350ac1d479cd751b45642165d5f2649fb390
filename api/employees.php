<?php
// api/employees.php (Protected endpoint)

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/middleware/auth.php';

require_auth();
$user = $_SESSION['user'];

// Only superadmin and accountant can modify employees (POST/PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireRole(['superadmin', 'accountant']);
}

// Load SecureDatabase model
require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $employees = $db->getAllEmployees();
            echo json_encode($employees ?: []);
            break;
        
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $result = $db->addEmployee($data);
            echo json_encode(['success' => true, 'id' => $result]);
            break;
        
        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Employee ID required']);
                break;
            }
            $result = $db->updateEmployee((int)$data['id'], $data);
            echo json_encode(['success' => true]);
            break;
        
        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Employee ID required']);
                break;
            }
            $result = $db->deleteEmployee((int)$id);
            echo json_encode(['success' => true, 'deleted' => $result]);
            break;
        
        default:
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
