<?php
// api/period/set.php - Save a new period with overlap detection

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../middleware/auth.php';

require_auth();
requireRole(['superadmin', 'accountant']);

$input = json_decode(file_get_contents('php://input'), true);
$periodStart = $input['period_start'] ?? null;
$periodEnd = $input['period_end'] ?? null;

if (!$periodStart || !$periodEnd) {
    http_response_code(400);
    echo json_encode(['error' => 'Period start and end required']);
    exit;
}

// Validate dates
if (!strtotime($periodStart) || !strtotime($periodEnd)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

if ($periodEnd < $periodStart) {
    http_response_code(400);
    echo json_encode(['error' => 'End date cannot be before start date']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = DatabaseConfig::getInstance();

    // Ensure period_settings table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS period_settings (
            id SERIAL PRIMARY KEY,
            current_period_start DATE NOT NULL,
            current_period_end DATE NOT NULL,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Check for overlapping periods
    // Two periods overlap if: (StartA <= EndB) AND (EndA >= StartB)
    $overlapStmt = $pdo->prepare("
        SELECT id, current_period_start, current_period_end
        FROM period_settings
        WHERE current_period_start <= :end
          AND current_period_end >= :start
    ");
    $overlapStmt->execute([
        ':start' => $periodStart,
        ':end' => $periodEnd
    ]);
    $overlapping = $overlapStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($overlapping) > 0) {
        $existing = $overlapping[0];
        http_response_code(409); // Conflict
        echo json_encode([
            'error' => 'Period overlaps with existing period',
            'overlap' => [
                'id' => $existing['id'],
                'start' => $existing['current_period_start'],
                'end' => $existing['current_period_end']
            ],
            'message' => "The period $periodStart to $periodEnd overlaps with an existing period (" . $existing['current_period_start'] . " - " . $existing['current_period_end'] . "). Please choose different dates."
        ]);
        exit;
    }

    // Check for exact duplicate
    $dupStmt = $pdo->prepare("
        SELECT id FROM period_settings
        WHERE current_period_start = :start AND current_period_end = :end
    ");
    $dupStmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
    $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);

    if ($duplicate) {
        // Update the existing record's timestamp instead of inserting
        $updateStmt = $pdo->prepare("
            UPDATE period_settings
            SET updated_by = :user, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':user' => $_SESSION['user_id'],
            ':id' => $duplicate['id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Period already exists — updated timestamp',
            'id' => $duplicate['id'],
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'duplicate' => true
        ]);
        exit;
    }

    // Insert new period
    $insertStmt = $pdo->prepare("
        INSERT INTO period_settings (current_period_start, current_period_end, updated_by, updated_at)
        VALUES (:start, :end, :user, CURRENT_TIMESTAMP)
        RETURNING id
    ");
    $insertStmt->execute([
        ':start' => $periodStart,
        ':end' => $periodEnd,
        ':user' => $_SESSION['user_id']
    ]);
    $newId = $insertStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Period saved successfully',
        'id' => $newId,
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
