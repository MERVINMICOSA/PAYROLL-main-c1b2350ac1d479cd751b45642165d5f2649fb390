<?php
// ===============================
// SAFE SESSION START
// ===============================
if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

// ===============================
// FORCE JSON OUTPUT ALWAYS
// ===============================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["ok" => true]);
    exit;
}

// ===============================
// GLOBAL ERROR SAFETY (NO HTML OUTPUT)
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
// DATABASE SAFETY CHECK
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
                http_response_code(400);
                echo json_encode(["error" => "Invalid JSON input"]);
                exit;
            }

            $employeeId = $input['employee_id'] ?? null;
            $periodStart = $input['period_start'] ?? null;
            $periodEnd = $input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                http_response_code(400);
                echo json_encode(["error" => "Missing required fields"]);
                exit;
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
} trait_exists