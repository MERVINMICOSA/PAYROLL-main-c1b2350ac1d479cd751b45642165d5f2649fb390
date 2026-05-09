<?php
// ===============================
// HARDENED GUARD ATTENDANCE API
// ===============================

if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// ===============================
// OPTIONS HANDLER
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["success" => true]);
    exit;
}

// ===============================
// AUTH CHECK (SAFE)
// ===============================
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]); // ALWAYS SAFE ARRAY RESPONSE
    exit;
}

// ===============================
// SAFE RESPONSE HELPER
// ===============================
function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ===============================
// DATABASE INIT (HARDENED)
// ===============================
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    json_response([]);
}

$db = parse_url($databaseUrl);

if (
    !$db ||
    !isset($db['host'], $db['user'], $db['pass'], $db['path'])
) {
    json_response([]);
}

$host = $db['host'];
$port = $db['port'] ?? '5432';
$user = $db['user'];
$pass = $db['pass'];
$dbname = ltrim($db['path'], '/');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    json_response([]);
}

// ===============================
// TABLE CREATION SAFE
// ===============================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance_guard (
        id SERIAL PRIMARY KEY,
        employee_id VARCHAR(50) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        daily_data JSONB DEFAULT '{}',
        rate DECIMAL(10,2) DEFAULT 0,
        days_worked INT DEFAULT 0,
        total_pay DECIMAL(12,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(employee_id, period_start, period_end)
    )
");

// ===============================
// ROUTER
// ===============================
switch ($_SERVER['REQUEST_METHOD']) {

    // ===========================
    // GET (ALWAYS ARRAY SAFE)
    // ===========================
    case 'GET':

        $periodStart = $_GET['period_start'] ?? null;
        $periodEnd = $_GET['period_end'] ?? null;

        try {

            if ($periodStart && $periodEnd) {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_guard 
                    WHERE period_start = :start AND period_end = :end
                ");
                $stmt->execute([
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_guard");
            }

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($results)) {
                $results = [];
            }

            $transformed = [];

            foreach ($results as $row) {
                $transformed[] = [
                    'employee_id' => $row['employee_id'] ?? '',
                    'period_start' => $row['period_start'] ?? '',
                    'period_end' => $row['period_end'] ?? '',
                    'daily_data' => json_decode($row['daily_data'] ?? '{}', true) ?: [],
                    'rate' => (float)($row['rate'] ?? 0),
                    'days_worked' => (int)($row['days_worked'] ?? 0),
                    'total_pay' => (float)($row['total_pay'] ?? 0)
                ];
            }

            json_response($transformed);

        } catch (Exception $e) {
            json_response([]);
        }

        break;

    // ===========================
    // POST (RATE + ATTENDANCE SAFE)
    // ===========================
    case 'POST':

        $input = json_decode(file_get_contents("php://input"), true);

        if (!is_array($input)) {
            json_response(["success" => false]);
        }

        $employeeId = $input['employee_id'] ?? null;
        $periodStart = $input['period_start'] ?? null;
        $periodEnd = $input['period_end'] ?? null;

        if (!$employeeId || !$periodStart || !$periodEnd) {
            json_response(["success" => false]);
        }

        try {

            // ===========================
            // RATE UPDATE MODE
            // ===========================
            if (isset($input['rate'])) {

                $stmt = $pdo->prepare("
                    SELECT id FROM attendance_guard
                    WHERE employee_id = :id
                    AND period_start = :start
                    AND period_end = :end
                ");

                $stmt->execute([
                    ':id' => $employeeId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ]);

                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $update = $pdo->prepare("
                        UPDATE attendance_guard
                        SET rate = :rate,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ");

                    $update->execute([
                        ':rate' => (float)$input['rate'],
                        ':id' => $existing['id']
                    ]);
                } else {
                    $insert = $pdo->prepare("
                        INSERT INTO attendance_guard
                        (employee_id, period_start, period_end, rate, daily_data, days_worked, total_pay)
                        VALUES (:id, :start, :end, :rate, '{}', 0, 0)
                    ");

                    $insert->execute([
                        ':id' => $employeeId,
                        ':start' => $periodStart,
                        ':end' => $periodEnd,
                        ':rate' => (float)$input['rate']
                    ]);
                }

                json_response(["success" => true]);
            }

            // ===========================
            // ATTENDANCE MODE
            // ===========================
            $date = $input['date'] ?? null;
            $present = $input['present'] ?? 0;

            if (!$date) {
                json_response(["success" => false]);
            }

            $stmt = $pdo->prepare("
                SELECT id, daily_data, rate
                FROM attendance_guard
                WHERE employee_id = :id
                AND period_start = :start
                AND period_end = :end
            ");

            $stmt->execute([
                ':id' => $employeeId,
                ':start' => $periodStart,
                ':end' => $periodEnd
            ]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $dailyData = [];

            if ($existing && !empty($existing['daily_data'])) {
                $decoded = json_decode($existing['daily_data'], true);
                if (is_array($decoded)) {
                    $dailyData = $decoded;
                }
            }

            $rate = $existing['rate'] ?? 0;

            $dailyData[$date] = $present == 1;

            $daysWorked = count(array_filter($dailyData));
            $totalPay = $daysWorked * $rate;

            if ($existing) {

                $update = $pdo->prepare("
                    UPDATE attendance_guard
                    SET daily_data = :data,
                        days_worked = :days,
                        total_pay = :pay,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $update->execute([
                    ':data' => json_encode($dailyData),
                    ':days' => $daysWorked,
                    ':pay' => $totalPay,
                    ':id' => $existing['id']
                ]);

            } else {

                $insert = $pdo->prepare("
                    INSERT INTO attendance_guard
                    (employee_id, period_start, period_end, daily_data, days_worked, total_pay, rate)
                    VALUES (:id, :start, :end, :data, :days, :pay, 0)
                ");

                $insert->execute([
                    ':id' => $employeeId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd,
                    ':data' => json_encode($dailyData),
                    ':days' => $daysWorked,
                    ':pay' => $totalPay
                ]);
            }

            json_response(["success" => true]);

        } catch (Exception $e) {
            json_response(["success" => false]);
        }

        break;

    default:
        json_response([]);
}
?>