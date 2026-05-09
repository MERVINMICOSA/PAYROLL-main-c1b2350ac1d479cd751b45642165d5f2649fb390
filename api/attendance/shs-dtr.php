<?php
// ===============================
// SAFE SESSION START
// ===============================
if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

// ===============================
// ALWAYS RETURN JSON SAFELY
// ===============================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["ok" => true]);
    exit;
}

// ===============================
// ERROR HANDLER (prevents HTML output)
// ===============================
/*
 * set_exception_handler
 * Converts uncaught exceptions into a safe JSON 500 response.
 */
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Server error",
        "message" => $e->getMessage()
    ]);
    exit;
});

// ===============================
// AUTH CHECK
// ===============================
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "error" => "Unauthorized"
    ]);
    exit;
}

// ===============================
// DATABASE SAFE CONNECT
// ===============================
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    http_response_code(500);
    echo json_encode([
        "error" => "Database not configured"
    ]);
    exit;
}

$db = parse_url($databaseUrl);

if (!$db || empty($db['host']) || empty($db['user']) || empty($db['path'])) {
    http_response_code(500);
    echo json_encode([
        "error" => "Invalid database configuration"
    ]);
    exit;
}

try {
    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'];
    $dbname = ltrim($db['path'], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ===============================
    // TABLE SAFE CREATE
    // ===============================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shs_dtr (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            daily_data JSONB DEFAULT '{}',
            total_hours DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");

    // ===============================
    // ROUTER
    // ===============================
/*
 * Router
 * Switches request method (GET/POST) to either read DTR records
 * or upsert daily attendance values into attendance_shs_dtr.
 */
switch ($_SERVER['REQUEST_METHOD']) {

        // -------------------------------
        // GET SAFE OUTPUT
        // -------------------------------
        case 'GET':
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_shs_dtr
                    WHERE period_start = :start AND period_end = :end
                    ORDER BY employee_id
                ");
                $stmt->execute([
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_shs_dtr ORDER BY employee_id");
            }

            $results = $stmt->fetchAll();

            $safe = [];

            foreach ($results as $row) {
                $dailyData = json_decode($row['daily_data'] ?? '{}', true);
                if (!is_array($dailyData)) {
                    $dailyData = [];
                }
                
                $safe[] = [
                    'employee_id' => $row['employee_id'] ?? '',
                    'period_start' => $row['period_start'] ?? '',
                    'period_end' => $row['period_end'] ?? '',
                    'daily_data' => $dailyData,
                    'total_hours' => (float)($row['total_hours'] ?? 0)
                ];
            }

            echo json_encode($safe);
            break;

        // -------------------------------
        // POST SAFE INPUT
        // -------------------------------
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);

            if (!$input) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid JSON input"]);
                exit;
            }

            $employeeId = $input['employee_id'] ?? null;
            $periodStart = $input['period_start'] ?? null;
            $periodEnd = $input['period_end'] ?? null;
            $date = $input['date'] ?? null;
            
            // Handle both 'hours' (for DTR) and 'present' (for checkboxes)
            $value = 0;
            if (isset($input['hours'])) {
                $value = (float)$input['hours'];
            } elseif (isset($input['present'])) {
                $value = (int)$input['present'];
            }

            if (!$employeeId || !$periodStart || !$periodEnd || !$date) {
                http_response_code(400);
                echo json_encode(["error" => "Missing required fields: employee_id, period_start, period_end, date"]);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT id, daily_data
                    FROM attendance_shs_dtr
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

                $dailyData = [];

                if ($existing) {
                    $decoded = json_decode($existing['daily_data'], true);
                    if (is_array($decoded)) {
                        $dailyData = $decoded;
                    }
                }

                $dailyData[$date] = $value;
                $totalHours = array_sum($dailyData);

                if ($existing) {
                    $update = $pdo->prepare("
                        UPDATE attendance_shs_dtr
                        SET daily_data = :data,
                            total_hours = :total,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                    ");

                    $update->execute([
                        ':data' => json_encode($dailyData),
                        ':total' => $totalHours,
                        ':id' => $existing['id']
                    ]);
                } else {
                    $insert = $pdo->prepare("
                        INSERT INTO attendance_shs_dtr
                        (employee_id, period_start, period_end, daily_data, total_hours)
                        VALUES (:id, :start, :end, :data, :total)
                    ");

                    $insert->execute([
                        ':id' => $employeeId,
                        ':start' => $periodStart,
                        ':end' => $periodEnd,
                        ':data' => json_encode($dailyData),
                        ':total' => $totalHours
                    ]);
                }

                echo json_encode([
                    "success" => true,
                    "total_hours" => $totalHours,
                    "message" => "Data saved successfully"
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "error" => "Database error",
                    "message" => $e->getMessage()
                ]);
            }
            break;

        // -------------------------------
        // INVALID METHOD SAFE RESPONSE
        // -------------------------------
        default:
            http_response_code(405);
            echo json_encode([
                "error" => "Method not allowed"
            ]);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Database error",
        "message" => $e->getMessage()
    ]);
}