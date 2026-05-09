<?php

require_once __DIR__ . '/../core/bootstrap.php';

require_auth();

// ===========================
// DATABASE SAFE INIT
// ===========================
$pdo = bootstrapGetPdo('require');

// ===========================
// TABLE INIT
// ===========================
$pdo->exec("
CREATE TABLE IF NOT EXISTS attendance_college_loading (
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

// ===========================
// SAFE INPUT PARSER
// ===========================
$input = json_decode(file_get_contents("php://input"), true) ?? [];

// ===========================
// ROUTER
// ===========================
switch ($_SERVER['REQUEST_METHOD']) {

    // ===========================
    // GET
    // ===========================
    case 'GET':
        $periodStart = $_GET['period_start'] ?? null;
        $periodEnd = $_GET['period_end'] ?? null;

        try {
            if ($periodStart && $periodEnd) {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_college_loading
                    WHERE period_start = :start AND period_end = :end
                ");
                $stmt->execute([
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_college_loading");
            }

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);

        } catch (Exception $e) {
            jsonError('Failed to fetch data', 500, $e->getMessage());
        }

        break;

    // ===========================
    // POST
    // ===========================
    case 'POST':

        $employeeId = $input['employee_id'] ?? null;
        $periodStart = $input['period_start'] ?? null;
        $periodEnd = $input['period_end'] ?? null;

        if (!$employeeId || !$periodStart || !$periodEnd) {
            jsonError('Missing required fields', 400);
        }

        // SAFE DATA SANITIZATION
        $subject = trim((string)($input['subject'] ?? ''));

        $mon = (float)($input['mon'] ?? 0);
        $tue = (float)($input['tue'] ?? 0);
        $wed = (float)($input['wed'] ?? 0);
        $thu = (float)($input['thu'] ?? 0);
        $fri = (float)($input['fri'] ?? 0);
        $sat = (float)($input['sat'] ?? 0);
        $sun = (float)($input['sun'] ?? 0);

        try {
            $stmt = $pdo->prepare("
                SELECT id FROM attendance_college_loading
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
                ':subject' => $subject,
                ':mon' => $mon,
                ':tue' => $tue,
                ':wed' => $wed,
                ':thu' => $thu,
                ':fri' => $fri,
                ':sat' => $sat,
                ':sun' => $sun
            ];

            if ($existing) {
                $data[':id'] = $existing['id'];

                $update = $pdo->prepare("
                    UPDATE attendance_college_loading
                    SET
                        subject = :subject,
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

                $update->execute($data);

            } else {
                $insert = $pdo->prepare("
                    INSERT INTO attendance_college_loading (
                        employee_id, period_start, period_end,
                        subject,
                        mon, tue, wed, thu, fri, sat, sun
                    )
                    VALUES (
                        :id, :start, :end,
                        :subject,
                        :mon, :tue, :wed, :thu, :fri, :sat, :sun
                    )
                ");

                $insert->execute(array_merge([
                    ':id' => $employeeId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ], $data));
            }

            echo json_encode([
                'success' => true
            ]);

        } catch (Exception $e) {
            jsonError('Database operation failed', 500, $e->getMessage());
        }

        break;
}