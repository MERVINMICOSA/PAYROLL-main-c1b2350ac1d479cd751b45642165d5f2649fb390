<?php
// ===============================
// SAFE SESSION START
// ===============================
require_once __DIR__ . '/../_bootstrap.php';
bootstrapStartSession();
bootstrapRequireAuth();

// ===============================
// GLOBAL ERROR SAFETY (NO HTML OUTPUT)
// ===============================
/*
 * set_exception_handler
 * Converts uncaught exceptions into a safe JSON 500 response.
 */
set_exception_handler(function($e) {
    jsonError('Server error', 500, $e->getMessage());
});

// ===============================
// DATABASE SAFETY CHECK
// ===============================
try {
    $pdo = bootstrapGetPdo('require');

    // ===============================
    // SAFE TABLE CREATION
    // ===============================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shs_loading (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            subject TEXT,
            mon DECIMAL(8,2) DEFAULT 0,
            tue DECIMAL(8,2) DEFAULT 0,
            wed DECIMAL(8,2) DEFAULT 0,
            thu DECIMAL(8,2) DEFAULT 0,
            fri DECIMAL(8,2) DEFAULT 0,
            sat DECIMAL(8,2) DEFAULT 0,
            sun DECIMAL(8,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");

    // ===============================
    // ROUTER
    // ===============================
    switch ($_SERVER['REQUEST_METHOD']) {

        // -------------------------
        // GET SAFE RESPONSE
        // -------------------------
        case 'GET':
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_shs_loading
                    WHERE period_start = :start AND period_end = :end
                ");
                $stmt->execute([
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_shs_loading");
            }

            $rows = $stmt->fetchAll();

            $safe = [];

            foreach ($rows as $row) {
                $safe[] = [
                    'employee_id' => $row['employee_id'] ?? '',
                    'period_start' => $row['period_start'] ?? '',
                    'period_end' => $row['period_end'] ?? '',
                    'subject' => $row['subject'] ?? '',
                    'mon' => (float)($row['mon'] ?? 0),
                    'tue' => (float)($row['tue'] ?? 0),
                    'wed' => (float)($row['wed'] ?? 0),
                    'thu' => (float)($row['thu'] ?? 0),
                    'fri' => (float)($row['fri'] ?? 0),
                    'sat' => (float)($row['sat'] ?? 0),
                    'sun' => (float)($row['sun'] ?? 0)
                ];
            }

            echo json_encode($safe);
            break;

        // -------------------------
        // POST SAFE INPUT
        // -------------------------
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);

            if (!$input) {
                jsonError('Invalid JSON input', 400);
            }

            $employeeId = $input['employee_id'] ?? null;
            $periodStart = $input['period_start'] ?? null;
            $periodEnd = $input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                jsonError('Missing required fields', 400);
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM attendance_shs_loading
                WHERE employee_id = :id
                AND period_start = :start
                AND period_end = :end
            ");

            $stmt->execute([
                ':id' => $employeeId,
                ':start' => $periodStart,
                ':end' => $periodEnd
            ]);

            $existing = $stmt->fetch();

            $data = [
                ':subject' => $input['subject'] ?? '',
                ':mon' => (float)($input['mon'] ?? 0),
                ':tue' => (float)($input['tue'] ?? 0),
                ':wed' => (float)($input['wed'] ?? 0),
                ':thu' => (float)($input['thu'] ?? 0),
                ':fri' => (float)($input['fri'] ?? 0),
                ':sat' => (float)($input['sat'] ?? 0),
                ':sun' => (float)($input['sun'] ?? 0)
            ];

            if ($existing) {
                $update = $pdo->prepare("
                    UPDATE attendance_shs_loading
                    SET subject = :subject,
                        mon = :mon,
                        tue = :tue,
                        wed = :wed,
                        thu = :thu,
                        fri = :fri,
                        sat = :sat,
                        sun = :sun,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $update->execute(array_merge($data, [
                    ':id' => $existing['id']
                ]));
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO attendance_shs_loading
                    (employee_id, period_start, period_end, subject, mon, tue, wed, thu, fri, sat, sun)
                    VALUES (:id, :start, :end, :subject, :mon, :tue, :wed, :thu, :fri, :sat, :sun)
                ");

                $insert->execute(array_merge([
                    ':id' => $employeeId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ], $data));
            }

            echo json_encode([
                "success" => true
            ]);
            break;

        // -------------------------
        // INVALID METHOD
        // -------------------------
        default:
            jsonError('Method not allowed', 405);
    }

} catch (PDOException $e) {
    jsonError('Database error', 500, $e->getMessage());
}