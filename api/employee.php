<?php
// api/employee.php - Get single employee endpoint

require_once __DIR__ . '/core/bootstrap.php';

require_auth();

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