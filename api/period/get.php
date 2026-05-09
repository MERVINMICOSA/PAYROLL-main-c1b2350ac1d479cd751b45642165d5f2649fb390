<?php
// api/period/get.php - Get current/most recent period

require_once __DIR__ . '/../core/bootstrap.php';

require_auth();

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

