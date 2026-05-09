<?php
// api/debug/attendance-by-period-counts.php
// Debug endpoint: returns counts for each attendance table for a given period
// Usage (auth required):
//   GET /api/debug/attendance-by-period-counts.php?period_start=YYYY-MM-DD&period_end=YYYY-MM-DD
// If period_start/end are not provided, it uses the most recently updated period in period_settings.

require_once __DIR__ . '/../config/session-start.php';
require_once __DIR__ . '/../config/cors-headers.php';
require_once __DIR__ . '/../middleware/auth.php';

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authenticated session (debug)
validateSession();

// Ensure period_settings table exists (some deployments may have an older/partial schema)
try {
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        http_response_code(500);
        echo json_encode(['error' => 'DATABASE_URL not set']);
        exit;
    }

    $dbUrl = parse_url($databaseUrl);
    $host = $dbUrl['host'];
    $port = $dbUrl['port'] ?? '5432';
    $user = $dbUrl['user'];
    $pass = $dbUrl['pass'];
    $dbname = ltrim($dbUrl['path'], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS period_settings (\n            id SERIAL PRIMARY KEY,\n            current_period_start DATE,\n            current_period_end DATE,\n            updated_by INT,\n            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        )\n    ");

} catch (Exception $e) {
    // Continue; later query will surface any real issue.
}


$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    http_response_code(500);
    echo json_encode(['error' => 'DATABASE_URL not set']);
    exit;
}

try {
    $db = parse_url($databaseUrl);
    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'];
    $dbname = ltrim($db['path'], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

// Determine period
    $periodStart = $_GET['period_start'] ?? null;
    $periodEnd = $_GET['period_end'] ?? null;

    // If explicit period not provided, use latest record
    if (!$periodStart || !$periodEnd) {
        $stmt = $pdo->prepare(
            "SELECT current_period_start, current_period_end, updated_at
             FROM period_settings
             WHERE current_period_start IS NOT NULL AND current_period_end IS NOT NULL
             ORDER BY updated_at DESC
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'No period_settings found (no current_period_start/current_period_end)']);
            exit;
        }
        $periodStart = $row['current_period_start'];
        $periodEnd = $row['current_period_end'];
    }

    $tables = [
        'attendance_eda',
        'attendance_shs_loading',
        'attendance_shs_dtr',
        'attendance_college_loading',
        'attendance_college_dtr',
        'attendance_admin_pay',
        'attendance_admin_master',
        'attendance_faculty_shs',
        'attendance_faculty_college',
        'attendance_guard',
        'attendance_sa',
    ];

    $results = [
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'checked_tables' => []
    ];

    foreach ($tables as $table) {
        // Check if table exists
        $existsStmt = $pdo->prepare("SELECT to_regclass(:t) AS regclass");
        $existsStmt->execute([':t' => "public.$table"]);
        $reg = $existsStmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg || empty($reg['regclass'])) {
            $results['checked_tables'][] = [
                'table' => $table,
                'exists' => false
            ];
            continue;
        }

        // Count rows for exact period
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM public.$table WHERE period_start = :start AND period_end = :end"
        );
        $countStmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        // Distinct employees (if employee_id exists)
        $empExistsStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = :t
               AND column_name = 'employee_id'"
        );
        $empExistsStmt->execute([':t' => $table]);
        $empColExists = (int)($empExistsStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) > 0;

        $empCount = null;
        if ($empColExists) {
            $empStmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT employee_id) AS c
                 FROM public.$table
                 WHERE period_start = :start AND period_end = :end"
            );
            $empStmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            $empCount = (int)($empStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        }

        $results['checked_tables'][] = [
            'table' => $table,
            'exists' => true,
            'rows_for_period' => $total,
            'distinct_employees_for_period' => $empCount,
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

