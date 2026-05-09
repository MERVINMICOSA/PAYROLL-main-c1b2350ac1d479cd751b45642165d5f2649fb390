<?php
// api/period/list.php - Get all saved periods

require_once __DIR__ . '/../core/bootstrap.php';

require_auth();

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
