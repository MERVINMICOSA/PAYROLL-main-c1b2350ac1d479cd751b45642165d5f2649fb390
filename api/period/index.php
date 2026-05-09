<?php
/**
 * api/period/index.php
 * Period Management API
 * Handles period creation, validation, and retrieval
 * Prevents overlapping periods
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetPeriod($db);
            break;
            
        case 'POST':
            handleCreatePeriod($db);
            break;
            
        case 'PUT':
            handleUpdatePeriod($db);
            break;
            
        case 'DELETE':
            handleDeletePeriod($db);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetPeriod($db) {
    $type = $_GET['type'] ?? 'all'; // 'all', 'active', 'specific', 'overlaps'
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    switch ($type) {
        case 'all':
            // Get all periods
            $periods = $db->fetchAll("
                SELECT * FROM attendance_periods 
                ORDER BY period_start DESC
            ");
            echo json_encode($periods ?: []);
            break;
            
        case 'active':
            // Get current active period
            $today = date('Y-m-d');
            $period = $db->fetchOne("
                SELECT * FROM attendance_periods 
                WHERE period_start <= ? AND period_end >= ?
                ORDER BY period_start DESC LIMIT 1
            ", [$today, $today]);
            echo json_encode($period ?: ['error' => 'No active period']);
            break;
            
        case 'specific':
            // Get specific period
            if (!$startDate || !$endDate) {
                http_response_code(400);
                echo json_encode(['error' => 'start_date and end_date required']);
                break;
            }
            
            $period = $db->fetchOne("
                SELECT * FROM attendance_periods 
                WHERE period_start = ? AND period_end = ?
                LIMIT 1
            ", [$startDate, $endDate]);
            
            echo json_encode($period ?: ['error' => 'Period not found']);
            break;
            
        case 'overlaps':
            // Check for overlapping periods
            if (!$startDate || !$endDate) {
                http_response_code(400);
                echo json_encode(['error' => 'start_date and end_date required']);
                break;
            }
            
            $overlaps = $db->fetchAll("
                SELECT * FROM attendance_periods 
                WHERE NOT (period_end < ? OR period_start > ?)
                ORDER BY period_start
            ", [$startDate, $endDate]);
            
            echo json_encode($overlaps ?: []);
            break;
    }
}

function handleCreatePeriod($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($data['period_start']) || !isset($data['period_end'])) {
        http_response_code(400);
        echo json_encode(['error' => 'period_start and period_end required']);
        exit;
    }
    
    $periodStart = $data['period_start'];
    $periodEnd = $data['period_end'];
    $description = $data['description'] ?? null;
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }
    
    // Validate start < end
    if (strtotime($periodStart) >= strtotime($periodEnd)) {
        http_response_code(400);
        echo json_encode(['error' => 'period_start must be before period_end']);
        exit;
    }
    
    // Check for overlaps
    $overlaps = $db->fetchAll("
        SELECT id FROM attendance_periods 
        WHERE NOT (period_end < ? OR period_start > ?)
    ", [$periodStart, $periodEnd]);
    
    if (!empty($overlaps)) {
        http_response_code(409);
        echo json_encode([
            'error' => 'Period overlaps with existing period(s)',
            'conflicting_periods' => $overlaps
        ]);
        exit;
    }
    
    // Check if exact period already exists
    $exists = $db->fetchOne("
        SELECT id FROM attendance_periods 
        WHERE period_start = ? AND period_end = ?
    ", [$periodStart, $periodEnd]);
    
    if ($exists) {
        http_response_code(409);
        echo json_encode(['error' => 'Period already exists']);
        exit;
    }
    
    // Create period
    $id = $db->insert('attendance_periods', [
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'description' => $description,
        'status' => 'active'
    ]);
    
    echo json_encode([
        'success' => true,
        'id' => $id,
        'message' => "Period created successfully: {$periodStart} to {$periodEnd}"
    ]);
}

function handleUpdatePeriod($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'id required']);
        exit;
    }
    
    $id = $data['id'];
    $periodStart = $data['period_start'] ?? null;
    $periodEnd = $data['period_end'] ?? null;
    
    // Get current period
    $current = $db->fetchOne("SELECT * FROM attendance_periods WHERE id = ?", [$id]);
    
    if (!$current) {
        http_response_code(404);
        echo json_encode(['error' => 'Period not found']);
        exit;
    }
    
    // Use existing dates if not provided
    if (!$periodStart) $periodStart = $current['period_start'];
    if (!$periodEnd) $periodEnd = $current['period_end'];
    
    // If dates changed, check for overlaps (excluding current period)
    if ($periodStart !== $current['period_start'] || $periodEnd !== $current['period_end']) {
        $overlaps = $db->fetchAll("
            SELECT id FROM attendance_periods 
            WHERE id != ? AND NOT (period_end < ? OR period_start > ?)
        ", [$id, $periodStart, $periodEnd]);
        
        if (!empty($overlaps)) {
            http_response_code(409);
            echo json_encode(['error' => 'Updated period would overlap with existing period(s)']);
            exit;
        }
    }
    
    // Update period
    $updateData = [];
    if (isset($data['period_start'])) $updateData['period_start'] = $data['period_start'];
    if (isset($data['period_end'])) $updateData['period_end'] = $data['period_end'];
    if (isset($data['description'])) $updateData['description'] = $data['description'];
    if (isset($data['status'])) $updateData['status'] = $data['status'];
    
    $db->update('attendance_periods', $updateData, 'id = ?', [$id]);
    
    echo json_encode(['success' => true, 'message' => 'Period updated']);
}

function handleDeletePeriod($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'id required']);
        exit;
    }
    
    // Check if period exists and has attendance data
    $period = $db->fetchOne("SELECT * FROM attendance_periods WHERE id = ?", [$id]);
    
    if (!$period) {
        http_response_code(404);
        echo json_encode(['error' => 'Period not found']);
        exit;
    }
    
    $attendanceCount = $db->fetchOne("
        SELECT COUNT(*) as count FROM attendance 
        WHERE period_start = ? AND period_end = ?
    ", [$period['period_start'], $period['period_end']]);
    
    if ($attendanceCount['count'] > 0) {
        http_response_code(409);
        echo json_encode([
            'error' => 'Cannot delete period with existing attendance records',
            'attendance_count' => $attendanceCount['count']
        ]);
        exit;
    }
    
    // Delete period
    $db->delete('attendance_periods', 'id = ?', [$id]);
    
    echo json_encode(['success' => true, 'message' => 'Period deleted']);
}
?>
