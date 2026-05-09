<?php
// api/period/get.php - Get current/most recent period

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

    // Get the most recently updated period
    $stmt = $pdo->prepare("
        SELECT id, current_period_start, current_period_end, updated_at
        FROM period_settings
        WHERE current_period_start IS NOT NULL AND current_period_end IS NOT NULL
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $period = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$period || !$period['current_period_start']) {
        // Default to current semi-month period
        $today = new DateTime();
        $day = (int)$today->format('d');

        if ($day <= 15) {
            $start = $today->format('Y-m-01');
            $end = $today->format('Y-m-15');
        } else {
            $start = $today->format('Y-m-16');
            $end = $today->format('Y-m-t');
        }

        echo json_encode([
            'current_period_start' => $start,
            'current_period_end' => $end,
            'is_default' => true,
            'period_count' => 0
        ]);
    } else {
        // Count total saved periods
        $countStmt = $pdo->query("SELECT COUNT(*) FROM period_settings WHERE current_period_start IS NOT NULL");
        $periodCount = (int) $countStmt->fetchColumn();

        echo json_encode([
            'id' => $period['id'],
            'current_period_start' => $period['current_period_start'],
            'current_period_end' => $period['current_period_end'],
            'updated_at' => $period['updated_at'],
            'is_default' => false,
            'period_count' => $periodCount
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

