<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    send_json(['error' => 'Unauthorized'], 401);
}

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    send_json(['error' => 'Database not configured'], 500);
}

try {
    $db = parse_url($databaseUrl);

    if (!$db || empty($db['host']) || empty($db['user']) || empty($db['path'])) {
        send_json(['error' => 'Invalid database config'], 500);
    }

    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'] ?? '';
    $dbname = ltrim($db['path'], '/');

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_eda (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            lates NUMERIC DEFAULT 0,
            absences NUMERIC DEFAULT 0,
            overtime NUMERIC DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $start = $_GET['period_start'] ?? null;
        $end = $_GET['period_end'] ?? null;

        if ($start && $end) {
            $stmt = $pdo->prepare("
                SELECT * FROM attendance_eda
                WHERE period_start = :start
                AND period_end = :end
                AND status = 'active'
            ");
            $stmt->execute(['start' => $start, 'end' => $end]);
        } else {
            $stmt = $pdo->query("SELECT * FROM attendance_eda WHERE status = 'active'");
        }

        $data = $stmt->fetchAll();
        send_json($data);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            send_json(['error' => 'Invalid JSON body'], 400);
        }

        $employeeId = $input['employee_id'] ?? null;
        $start = $input['period_start'] ?? null;
        $end = $input['period_end'] ?? null;

        if (!$employeeId || !$start || !$end) {
            send_json(['error' => 'Missing required fields'], 400);
        }

        $lates = $input['lates'] ?? 0;
        $absences = $input['absences'] ?? 0;
        $overtime = $input['overtime'] ?? 0;

        $check = $pdo->prepare("
            SELECT id FROM attendance_eda
            WHERE employee_id = :id
            AND period_start = :start
            AND period_end = :end
        ");

        $check->execute([
            'id' => $employeeId,
            'start' => $start,
            'end' => $end
        ]);

        $existing = $check->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE attendance_eda
                SET lates = :lates,
                    absences = :absences,
                    overtime = :overtime,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute([
                'lates' => $lates,
                'absences' => $absences,
                'overtime' => $overtime,
                'id' => $existing['id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_eda
                (employee_id, period_start, period_end, lates, absences, overtime)
                VALUES (:id, :start, :end, :lates, :absences, :overtime)
            ");

            $stmt->execute([
                'id' => $employeeId,
                'start' => $start,
                'end' => $end,
                'lates' => $lates,
                'absences' => $absences,
                'overtime' => $overtime
            ]);
        }

        send_json(['success' => true]);
    }

    send_json(['error' => 'Method not allowed'], 405);

} catch (Exception $e) {
    send_json(['error' => 'Server error'], 500);
}
?>