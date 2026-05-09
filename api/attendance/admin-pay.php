<?php
declare(strict_types=1);

/* =========================
   SESSION SECURITY
========================= */
if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

/* =========================
   HEADERS
========================= */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* =========================
   AUTH CHECK
========================= */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/* =========================
   DB CONNECTION
========================= */
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
        throw new Exception("Invalid DB config");
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

/* =========================
   TABLES
========================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance_admin_pay (
        id SERIAL PRIMARY KEY,
        employee_id VARCHAR(50) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        admin_hours DECIMAL(8,2) DEFAULT 0,
        total_pay DECIMAL(12,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(employee_id, period_start, period_end)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        key VARCHAR(100) PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

/* =========================
   HELPERS
========================= */
function jsonInput(): array {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

function safeDate($date): ?string {
    if (!$date) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : null;
}

function safeFloat($value): float {
    return is_numeric($value) ? (float)$value : 0.0;
}

/* =========================
   GLOBAL RATE
========================= */
function getGlobalAdminRate(PDO $pdo): float {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'global_admin_rate'");
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? (float)$row['value'] : 70.0;
}

function setGlobalAdminRate(PDO $pdo, float $rate): void {
    $stmt = $pdo->prepare("
        INSERT INTO settings (key, value)
        VALUES ('global_admin_rate', :rate)
        ON CONFLICT (key)
        DO UPDATE SET value = :rate, updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([':rate' => $rate]);
}

/* =========================
   ROUTER
========================= */
try {

    switch ($_SERVER['REQUEST_METHOD']) {

        /* =========================
           GET
        ========================= */
        case 'GET':
            $start = safeDate($_GET['period_start'] ?? null);
            $end   = safeDate($_GET['period_end'] ?? null);

            $globalRate = getGlobalAdminRate($pdo);

            if ($start && $end) {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance_admin_pay
                    WHERE period_start = :start AND period_end = :end
                ");
                $stmt->execute([
                    ':start' => $start,
                    ':end' => $end
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_admin_pay");
            }

            echo json_encode([
                'global_rate' => $globalRate,
                'records' => $stmt->fetchAll()
            ]);
            break;

        /* =========================
           POST
        ========================= */
        case 'POST':
            $input = jsonInput();

            /* UPDATE GLOBAL RATE */
            if (isset($input['global_rate'])) {
                $rate = safeFloat($input['global_rate']);

                if ($rate < 0 || $rate > 100000) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid rate']);
                    exit;
                }

                setGlobalAdminRate($pdo, $rate);

                echo json_encode([
                    'success' => true,
                    'global_rate' => $rate
                ]);
                exit;
            }

            /* NORMAL PAYROLL UPDATE */
            $employeeId  = trim($input['employee_id'] ?? '');
            $start       = safeDate($input['period_start'] ?? null);
            $end         = safeDate($input['period_end'] ?? null);
            $hours       = safeFloat($input['admin_hours'] ?? 0);

            if (!$employeeId || !$start || !$end) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input']);
                exit;
            }

            $globalRate = getGlobalAdminRate($pdo);
            $totalPay = $hours * $globalRate;

            $stmt = $pdo->prepare("
                SELECT id FROM attendance_admin_pay
                WHERE employee_id = :id AND period_start = :start AND period_end = :end
            ");

            $stmt->execute([
                ':id' => $employeeId,
                ':start' => $start,
                ':end' => $end
            ]);

            $existing = $stmt->fetch();

            if ($existing) {
                $update = $pdo->prepare("
                    UPDATE attendance_admin_pay
                    SET admin_hours = :hours,
                        total_pay = :pay,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $update->execute([
                    ':hours' => $hours,
                    ':pay' => $totalPay,
                    ':id' => $existing['id']
                ]);

            } else {
                $insert = $pdo->prepare("
                    INSERT INTO attendance_admin_pay
                    (employee_id, period_start, period_end, admin_hours, total_pay)
                    VALUES (:id, :start, :end, :hours, :pay)
                ");

                $insert->execute([
                    ':id' => $employeeId,
                    ':start' => $start,
                    ':end' => $end,
                    ':hours' => $hours,
                    ':pay' => $totalPay
                ]);
            }

            echo json_encode([
                'success' => true,
                'total_pay' => $totalPay
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
