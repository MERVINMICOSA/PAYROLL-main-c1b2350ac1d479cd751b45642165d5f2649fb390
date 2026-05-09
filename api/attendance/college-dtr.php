<?php

if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        throw new Exception("Missing DATABASE_URL");
    }

    $db = parse_url($databaseUrl);

    $host = $db['host'] ?? '';
    $port = $db['port'] ?? '5432';
    $user = $db['user'] ?? '';
    $pass = $db['pass'] ?? '';
    $dbname = isset($db['path']) ? ltrim($db['path'], '/') : '';

    if (!$host || !$user || !$dbname) {
        throw new Exception("Invalid database configuration");
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

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
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input']);
                exit;
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
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error'
    ]);
}
?>
