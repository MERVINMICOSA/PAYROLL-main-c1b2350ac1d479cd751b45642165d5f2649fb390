<?php
declare(strict_types=1);

// ===========================
// SESSION HANDLING (SAFE)
// ===========================
if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

// ===========================
// HEADERS
// ===========================
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// ===========================
// PRE-FLIGHT REQUEST
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===========================
// AUTH CHECK (HARDENED)
// ===========================
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

// ===========================
// DATABASE CONNECTION SAFETY
// ===========================
$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration missing']);
    exit;
}

$db = parse_url($databaseUrl);

if (!$db || !isset($db['host'], $db['user'], $db['pass'], $db['path'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid database URL']);
    exit;
}

$host = $db['host'];
$port = $db['port'] ?? '5432';
$user = $db['user'];
$pass = $db['pass'];
$dbname = ltrim($db['path'], '/');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// ===========================
// TABLE CREATE (SAFE)
// ===========================
$pdo->exec("
CREATE TABLE IF NOT EXISTS attendance_faculty_shs (
    id SERIAL PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    regular_hours DECIMAL(8,2) DEFAULT 0,
    admin_hours DECIMAL(8,2) DEFAULT 0,

    gross_pay DECIMAL(12,2) DEFAULT 0,

    sss DECIMAL(10,2) DEFAULT 0,
    philhealth DECIMAL(10,2) DEFAULT 0,
    pagibig DECIMAL(10,2) DEFAULT 0,
    withholding_tax DECIMAL(10,2) DEFAULT 0,

    sss_loan DECIMAL(10,2) DEFAULT 0,
    hdmf_loan DECIMAL(10,2) DEFAULT 0,
    cash_advance DECIMAL(10,2) DEFAULT 0,
    atm_deposit DECIMAL(10,2) DEFAULT 0,

    marketing_allowance DECIMAL(10,2) DEFAULT 0,
    net_pay DECIMAL(12,2) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(employee_id, period_start, period_end)
)
");

// ===========================
// SAFE INPUT READER
// ===========================
function post($key, $default = 0) {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) return $default;
    return $input[$key] ?? $default;
}

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
                    SELECT * FROM attendance_faculty_shs
                    WHERE period_start = :start AND period_end = :end
                ");
                $stmt->execute([
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ]);
            } else {
                $stmt = $pdo->query("SELECT * FROM attendance_faculty_shs");
            }

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch data']);
        }
        break;

    // ===========================
    // POST
    // ===========================
    case 'POST':

        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit;
        }

        $employeeId = $input['employee_id'] ?? null;
        $periodStart = $input['period_start'] ?? null;
        $periodEnd = $input['period_end'] ?? null;

        if (!$employeeId || !$periodStart || !$periodEnd) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        // ===========================
        // SAFE CASTING
        // ===========================
        $regularHours = (float)($input['regular_hours'] ?? $input['regular_hrs'] ?? 0);
        $adminHours   = (float)($input['admin_hours'] ?? $input['admin_hrs'] ?? 0);

        $sss = (float)($input['sss'] ?? 0);
        $philhealth = (float)($input['philhealth'] ?? 0);
        $pagibig = (float)($input['pagibig'] ?? 0);
        $wtax = (float)($input['withholding_tax'] ?? $input['wtax'] ?? 0);

        $sssLoan = (float)($input['sss_loan'] ?? 0);
        $hdmfLoan = (float)($input['hdmf_loan'] ?? 0);
        $cashAdvance = (float)($input['cash_advance'] ?? $input['cash_adv'] ?? 0);
        $atmDeposit = (float)($input['atm_deposit'] ?? $input['atm_dep'] ?? 0);

        $marketingAllowance = (float)($input['marketing_allowance'] ?? $input['marketing'] ?? 0);

        // ===========================
        // COMPUTATION (SAFE)
        // ===========================
        $grossPay = ($regularHours * 80) + ($adminHours * 70);

        $deductions =
            $sss + $philhealth + $pagibig + $wtax +
            $sssLoan + $hdmfLoan + $cashAdvance + $atmDeposit;

        $netPay = $grossPay - $deductions + $marketingAllowance;

        // prevent negative nonsense
        if ($netPay < 0) $netPay = 0;

        try {
            $stmt = $pdo->prepare("
                SELECT id FROM attendance_faculty_shs
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
                ':regular_hours' => $regularHours,
                ':admin_hours' => $adminHours,
                ':gross_pay' => $grossPay,
                ':sss' => $sss,
                ':philhealth' => $philhealth,
                ':pagibig' => $pagibig,
                ':withholding_tax' => $wtax,
                ':sss_loan' => $sssLoan,
                ':hdmf_loan' => $hdmfLoan,
                ':cash_advance' => $cashAdvance,
                ':atm_deposit' => $atmDeposit,
                ':marketing_allowance' => $marketingAllowance,
                ':net_pay' => $netPay
            ];

            if ($existing) {
                $data[':id'] = $existing['id'];

                $update = $pdo->prepare("
                    UPDATE attendance_faculty_shs
                    SET
                        regular_hours = :regular_hours,
                        admin_hours = :admin_hours,
                        gross_pay = :gross_pay,
                        sss = :sss,
                        philhealth = :philhealth,
                        pagibig = :pagibig,
                        withholding_tax = :withholding_tax,
                        sss_loan = :sss_loan,
                        hdmf_loan = :hdmf_loan,
                        cash_advance = :cash_advance,
                        atm_deposit = :atm_deposit,
                        marketing_allowance = :marketing_allowance,
                        net_pay = :net_pay,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $update->execute($data);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO attendance_faculty_shs (
                        employee_id, period_start, period_end,
                        regular_hours, admin_hours, gross_pay,
                        sss, philhealth, pagibig, withholding_tax,
                        sss_loan, hdmf_loan, cash_advance, atm_deposit,
                        marketing_allowance, net_pay
                    )
                    VALUES (
                        :id, :start, :end,
                        :regular_hours, :admin_hours, :gross_pay,
                        :sss, :philhealth, :pagibig, :withholding_tax,
                        :sss_loan, :hdmf_loan, :cash_advance, :atm_deposit,
                        :marketing_allowance, :net_pay
                    )
                ");

                $insert->execute(array_merge([
                    ':id' => $employeeId,
                    ':start' => $periodStart,
                    ':end' => $periodEnd
                ], $data));
            }

            echo json_encode([
                'success' => true,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database operation failed']);
        }

        break;
}
?>