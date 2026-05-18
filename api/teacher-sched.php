<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

require_auth();

set_exception_handler(function($e) {
    jsonError('Server error', 500, $e->getMessage());
});

$pdo = bootstrapGetPdo('require');
$method = $_SERVER['REQUEST_METHOD'];
$input = bootstrapJsonInput();

function teacherSchedTableForLevel(string $level): string
{
    if ($level === 'shs') {
        return 'attendance_shs_loading';
    }

    if ($level === 'college') {
        return 'attendance_college_loading';
    }

    jsonError('Invalid level', 400);
}

function teacherSchedEnsureTable(PDO $pdo, string $table): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS {$table} (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            subject TEXT,
            schedule_data JSONB DEFAULT '{}',
            mon DECIMAL(8,2) DEFAULT 0,
            tue DECIMAL(8,2) DEFAULT 0,
            wed DECIMAL(8,2) DEFAULT 0,
            thu DECIMAL(8,2) DEFAULT 0,
            fri DECIMAL(8,2) DEFAULT 0,
            sat DECIMAL(8,2) DEFAULT 0,
            sun DECIMAL(8,2) DEFAULT 0,
            total_hours DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");

    $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS schedule_data JSONB DEFAULT '{}'");
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS total_hours DECIMAL(10,2) DEFAULT 0");
}

function teacherSchedNormalizeSchedule($value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    return is_array($value) ? $value : [];
}

function teacherSchedSafeDate($date): ?string
{
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', (string)$date);
    return $d && $d->format('Y-m-d') === $date ? $date : null;
}

function teacherSchedSafeFloat($value): float
{
    return is_numeric($value) ? (float)$value : 0.0;
}

try {
    $level = $_GET['level'] ?? $input['level'] ?? '';
    $table = teacherSchedTableForLevel((string)$level);
    teacherSchedEnsureTable($pdo, $table);

    if ($method === 'GET') {
        $periodStart = teacherSchedSafeDate($_GET['period_start'] ?? null);
        $periodEnd = teacherSchedSafeDate($_GET['period_end'] ?? null);

        if ($periodStart && $periodEnd) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE period_start = :start AND period_end = :end ORDER BY employee_id");
            $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
        } else {
            $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY employee_id");
        }

        $rows = array_map(static function($row) use ($level) {
            return [
                'employee_id' => $row['employee_id'] ?? '',
                'period_start' => $row['period_start'] ?? '',
                'period_end' => $row['period_end'] ?? '',
                'subject' => teacherSchedSafeFloat($row['subject'] ?? 0),
                'schedule_data' => teacherSchedNormalizeSchedule($row['schedule_data'] ?? '{}'),
                'mon' => (float)($row['mon'] ?? 0),
                'tue' => (float)($row['tue'] ?? 0),
                'wed' => (float)($row['wed'] ?? 0),
                'thu' => (float)($row['thu'] ?? 0),
                'fri' => (float)($row['fri'] ?? 0),
                'sat' => (float)($row['sat'] ?? 0),
                'sun' => (float)($row['sun'] ?? 0),
                'total_hours' => (float)($row['total_hours'] ?? 0),
                'load_level' => $level,
            ];
        }, $stmt->fetchAll());

        jsonResponse($rows);
    }

    if ($method !== 'POST') {
        jsonError('Method not allowed', 405);
    }

    $employeeId = trim((string)($input['employee_id'] ?? ''));
    $periodStart = teacherSchedSafeDate($input['period_start'] ?? null);
    $periodEnd = teacherSchedSafeDate($input['period_end'] ?? null);

    if (!$employeeId || !$periodStart || !$periodEnd) {
        jsonError('Missing required fields', 400);
    }

    $mon = (float)($input['mon'] ?? 0);
    $tue = (float)($input['tue'] ?? 0);
    $wed = (float)($input['wed'] ?? 0);
    $thu = (float)($input['thu'] ?? 0);
    $fri = (float)($input['fri'] ?? 0);
    $sat = (float)($input['sat'] ?? 0);
    $sun = (float)($input['sun'] ?? 0);
    $totalHours = $mon + $tue + $wed + $thu + $fri + $sat + $sun;

    $stmt = $pdo->prepare("SELECT id, schedule_data FROM {$table} WHERE employee_id = :id AND period_start = :start AND period_end = :end");
    $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
    $existing = $stmt->fetch();

    $scheduleData = teacherSchedNormalizeSchedule($input['schedule_data'] ?? ($existing['schedule_data'] ?? '{}'));
    $data = [
        ':employee_id' => $employeeId,
        ':period_start' => $periodStart,
        ':period_end' => $periodEnd,
        ':subject' => teacherSchedSafeFloat($input['subject'] ?? 0),
        ':schedule_data' => json_encode($scheduleData),
        ':mon' => $mon,
        ':tue' => $tue,
        ':wed' => $wed,
        ':thu' => $thu,
        ':fri' => $fri,
        ':sat' => $sat,
        ':sun' => $sun,
        ':total_hours' => $totalHours,
    ];

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE {$table}
            SET subject = :subject,
                schedule_data = :schedule_data,
                mon = :mon,
                tue = :tue,
                wed = :wed,
                thu = :thu,
                fri = :fri,
                sat = :sat,
                sun = :sun,
                total_hours = :total_hours,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $update->execute([
            ':subject' => $data[':subject'],
            ':schedule_data' => $data[':schedule_data'],
            ':mon' => $data[':mon'],
            ':tue' => $data[':tue'],
            ':wed' => $data[':wed'],
            ':thu' => $data[':thu'],
            ':fri' => $data[':fri'],
            ':sat' => $data[':sat'],
            ':sun' => $data[':sun'],
            ':total_hours' => $data[':total_hours'],
            ':id' => $existing['id'],
        ]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO {$table}
                (employee_id, period_start, period_end, subject, schedule_data, mon, tue, wed, thu, fri, sat, sun, total_hours)
            VALUES
                (:employee_id, :period_start, :period_end, :subject, :schedule_data, :mon, :tue, :wed, :thu, :fri, :sat, :sun, :total_hours)
        ");
        $insert->execute($data);
    }

    jsonResponse([
        'employee_id' => $employeeId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'subject' => $data[':subject'],
        'schedule_data' => $scheduleData,
        'mon' => $mon,
        'tue' => $tue,
        'wed' => $wed,
        'thu' => $thu,
        'fri' => $fri,
        'sat' => $sat,
        'sun' => $sun,
        'total_hours' => $totalHours,
        'load_level' => $level,
    ]);
} catch (Throwable $e) {
    jsonError('Teacher schedule operation failed', 500, $e->getMessage());
}
