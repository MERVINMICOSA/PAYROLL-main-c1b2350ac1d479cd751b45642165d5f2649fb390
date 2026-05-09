<?php

require_once __DIR__ . '/../_bootstrap.php';
bootstrapStartSession();
bootstrapRequireAuth();

$pdo = bootstrapGetPdo('');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance_college_dtr (
        id SERIAL PRIMARY KEY,
        employee_id VARCHAR(50) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        daily_data JSONB DEFAULT '{}',
        total_hours DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(employee_id, period_start, period_end)
    )
");

function jsonInput(): array {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

function safeDate($date): ?string {
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date ? $date : null;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $start = safeDate($_GET['period_start'] ?? null);
            $end = safeDate($_GET['period_end'] ?? null);

            if ($start && $end) {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_college_dtr
                    WHERE period_start = :start AND period_end = :end
                    ORDER BY employee_id
                ");
                $stmt->execute([
                    ':start' => $start,
                    ':end' => $end
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_college_dtr ORDER BY employee_id");
            }

            $rows = $stmt->fetchAll();
            $output = [];
            foreach ($rows as $row) {
                $output[] = [
                    'employee_id' => $row['employee_id'],
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'daily_data' => json_decode($row['daily_data'] ?? '{}', true),
                    'total_hours' => (float)($row['total_hours'] ?? 0)
                ];
            }

            echo json_encode($output);
            break;

        case 'POST':
            $input = jsonInput();

            $employeeId = trim($input['employee_id'] ?? '');
            $periodStart = safeDate($input['period_start'] ?? null);
            $periodEnd = safeDate($input['period_end'] ?? null);
            $date = safeDate($input['date'] ?? null);

            $hours = null;
            if (isset($input['hours'])) {
                $hours = (float)$input['hours'];
            } elseif (isset($input['present'])) {
                $hours = ((int)$input['present'] === 1) ? 1.0 : 0.0;
            }

            if (!$employeeId || !$periodStart || !$periodEnd || !$date || $hours === null || $hours < 0) {
                jsonError('Invalid input', 400);
            }

            $stmt = $pdo->prepare("
                SELECT id, daily_data
                FROM attendance_college_dtr
                WHERE employee_id = :id AND period_start = :start AND period_end = :end
            ");
            $stmt->execute([
                ':id' => $employeeId,
                ':start' => $periodStart,
                ':end' => $periodEnd
            ]);

            $existing = $stmt->fetch();
            $dailyData = $existing ? json_decode($existing['daily_data'], true) : [];
            if (!is_array($dailyData)) {
                $dailyData = [];
            }

            $dailyData[$date] = $hours;
            $totalHours = array_sum(array_map(static fn($value) => (float)$value, $dailyData));

            if ($existing) {
                $update = $pdo->prepare("
                    UPDATE attendance_college_dtr
                    SET daily_data = :data,
                        total_hours = :hours,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $update->execute([
                    ':data' => json_encode($dailyData),
                    ':hours' => $totalHours,
                    ':id' => $existing['id']
                ]);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO attendance_college_dtr
                    (employee_id, period_start, period_end, daily_data, total_hours)
                    VALUES
                    (:id, :start, :end, :data, :hours)
                ");
                $insert->execute([
                    ':id' => $employeeId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd,
                    ':data' => json_encode($dailyData),
                    ':hours' => $totalHours
                ]);
            }

            echo json_encode([
                'success' => true,
                'total_hours' => $totalHours
            ]);
            break;

        default:
            jsonError('Method not allowed', 405);
    }
} catch (Throwable $e) {
    jsonError('Server error', 500, $e->getMessage());
}
