<?php
// api/payroll.php — Payroll data endpoint (session-protected)

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/middleware/auth.php';

require_auth();

// Only superadmin and accountant can modify payroll (POST/PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireRole(['superadmin', 'accountant']);
}

require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $payroll = $db->getAllPayroll();
            echo json_encode($payroll ?: []);
            break;

        case 'POST':
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (isset($data['action']) && $data['action'] === 'generate_from_attendance') {
                // Generate payroll from attendance for given period
                $period_start = $data['period_start'] ?? null;
                $period_end = $data['period_end'] ?? null;
                $period_display = $data['period_display'] ?? 'Custom Period';
                
                if (!$period_start || !$period_end) {
                    http_response_code(400);
                    echo json_encode(['error' => 'period_start and period_end required']);
                    break;
                }
                
                $generated = $db->generatePayrollFromAttendance($period_start, $period_end, $period_display);
                echo json_encode([
                    'success' => true, 
                    'count' => count($generated),
                    'message' => count($generated) . ' payroll records generated/updated'
                ]);
                break;
            }
            
            // Original addPayroll logic
            $id = $db->addPayroll($data);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Payroll ID required']);
                break;
            }
            $db->updatePayroll((int)$data['id'], $data);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Payroll ID required']);
                break;
            }
            $db->deletePayroll((int)$id);
            echo json_encode(['success' => true]);
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
