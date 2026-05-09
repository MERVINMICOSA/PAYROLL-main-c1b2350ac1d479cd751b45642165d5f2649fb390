<?php
// api/period/list.php - Get all saved periods

session_start();

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

header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please log in']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = DatabaseConfig::getInstance();
    
    // Get all periods sorted by most recent first
    $stmt = $pdo->prepare("
        SELECT id, current_period_start, current_period_end, updated_by, updated_at
        FROM period_settings 
        WHERE current_period_start IS NOT NULL AND current_period_end IS NOT NULL
        ORDER BY updated_at DESC
    ");
    $stmt->execute();
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formattedPeriods = [];
    foreach ($periods as $period) {
        $start = $period['current_period_start'];
        $end = $period['current_period_end'];
        $formattedPeriods[] = [
            'id' => $period['id'],
            'start' => $start,
            'end' => $end,
            'display' => date('M d, Y', strtotime($start)) . ' - ' . date('M d, Y', strtotime($end)),
            'updated_at' => $period['updated_at'],
            'updated_by' => $period['updated_by']
        ];
    }
    
    echo json_encode([
        'periods' => $formattedPeriods,
        'count' => count($formattedPeriods)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
