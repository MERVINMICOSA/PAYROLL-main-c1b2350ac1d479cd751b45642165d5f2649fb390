<?php
/**
 * UNIFIED ATTENDANCE API ROUTER
 * 
 * Single entry point for all attendance operations.
 * Auth check happens once at the top.
 * Routes to specific handlers based on 'type' parameter.
 */

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

// ============================================
// SINGLE AUTH CHECK (happens once here)
// ============================================
require_auth();

// ============================================
// GLOBAL SETUP
// ============================================
set_exception_handler(function($e) {
    jsonError('Server error', 500, $e->getMessage());
});

$pdo = bootstrapGetPdo('require');
$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? $_POST['type'] ?? 'eda'; // Default to EDA
$input = json_decode(file_get_contents("php://input"), true) ?? [];

// ============================================
// UNIFIED HANDLERS
// ============================================

class AttendanceRouter {
    private PDO $pdo;
    private string $type;
    private string $method;
    private array $input;

    public function __construct(PDO $pdo, string $type, string $method, array $input) {
        $this->pdo = $pdo;
        $this->type = $type;
        $this->method = $method;
        $this->input = $input;
    }

    public function route(): void {
        match($this->type) {
            'eda' => $this->handleEDA(),
            'admin-pay' => $this->handleAdminPay(),
            'admin-master' => $this->handleAdminMaster(),
            'shs-dtr' => $this->handleSHSDTR(),
            'shs-loading' => $this->handleSHSLoading(),
            'college-dtr' => $this->handleCollegeDTR(),
            'college-loading' => $this->handleCollegeLoading(),
            'faculty-shs' => $this->handleFacultySHS(),
            'faculty-college' => $this->handleFacultyCollege(),
            'guard' => $this->handleGuard(),
            'sa' => $this->handleSA(),
            default => jsonError('Unknown attendance type', 400)
        };
    }

    // ============================================
    // HELPERS
    // ============================================

    private function safeFloat($v): float {
        return is_numeric($v) ? (float)$v : 0.0;
    }

    private function safeDate($date): ?string {
        if (!$date) return null;
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date ? $date : null;
    }

    private function tableHasColumn(string $tableName, string $columnName): bool {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM information_schema.columns
            WHERE table_name = :table AND column_name = :column LIMIT 1
        ");
        $stmt->execute([':table' => $tableName, ':column' => $columnName]);
        return (bool)$stmt->fetchColumn();
    }

    // ============================================
    // EDA - Employee Daily Attendance
    // ============================================

    private function handleEDA(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $start = $_GET['period_start'] ?? null;
            $end = $_GET['period_end'] ?? null;

            if ($start && $end) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM attendance_eda
                    WHERE period_start = :start AND period_end = :end AND status = 'active'
                ");
                $stmt->execute(['start' => $start, 'end' => $end]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_eda WHERE status = 'active'");
            }
            echo json_encode($stmt->fetchAll());
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $start = $this->input['period_start'] ?? null;
            $end = $this->input['period_end'] ?? null;

            if (!$employeeId || !$start || !$end) {
                jsonError('Missing required fields', 400);
            }

            $lates = $this->input['lates'] ?? 0;
            $absences = $this->input['absences'] ?? 0;
            $overtime = $this->input['overtime'] ?? 0;

            $check = $this->pdo->prepare("
                SELECT id FROM attendance_eda
                WHERE employee_id = :id AND period_start = :start AND period_end = :end
            ");
            $check->execute(['id' => $employeeId, 'start' => $start, 'end' => $end]);
            $existing = $check->fetch();

            if ($existing) {
                $stmt = $this->pdo->prepare("
                    UPDATE attendance_eda
                    SET lates = :lates, absences = :absences, overtime = :overtime, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute(['lates' => $lates, 'absences' => $absences, 'overtime' => $overtime, 'id' => $existing['id']]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO attendance_eda (employee_id, period_start, period_end, lates, absences, overtime)
                    VALUES (:id, :start, :end, :lates, :absences, :overtime)
                ");
                $stmt->execute(['id' => $employeeId, 'start' => $start, 'end' => $end, 'lates' => $lates, 'absences' => $absences, 'overtime' => $overtime]);
            }
            echo json_encode(['success' => true]);
        }
    }

    // ============================================
    // ADMIN PAY
    // ============================================

    private function handleAdminPay(): void {
        $this->pdo->exec("
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
        $this->pdo->exec("ALTER TABLE attendance_admin_pay ADD COLUMN IF NOT EXISTS admin_hours DECIMAL(8,2) DEFAULT 0");
        $this->pdo->exec("ALTER TABLE attendance_admin_pay ADD COLUMN IF NOT EXISTS total_pay DECIMAL(12,2) DEFAULT 0");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key VARCHAR(100) PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        if ($this->method === 'GET') {
            $start = $this->safeDate($_GET['period_start'] ?? null);
            $end = $this->safeDate($_GET['period_end'] ?? null);
            $globalRate = $this->getGlobalAdminRate();

            if ($start && $end) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_admin_pay WHERE period_start = :start AND period_end = :end");
                $stmt->execute([':start' => $start, ':end' => $end]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_admin_pay");
            }
            echo json_encode(['global_rate' => $globalRate, 'records' => $stmt->fetchAll()]);
        } elseif ($this->method === 'POST') {
            if (isset($this->input['global_rate'])) {
                $rate = $this->safeFloat($this->input['global_rate']);
                if ($rate < 0 || $rate > 100000) {
                    jsonError('Invalid rate', 400);
                }
                $this->setGlobalAdminRate($rate);
                if (empty($this->input['employee_id'])) {
                    echo json_encode(['success' => true, 'global_rate' => $rate]);
                    exit;
                }
            }

            $employeeId = trim($this->input['employee_id'] ?? '');
            $start = $this->safeDate($this->input['period_start'] ?? null);
            $end = $this->safeDate($this->input['period_end'] ?? null);
            $hours = $this->safeFloat($this->input['admin_hours'] ?? 0);

            if (!$employeeId || !$start || !$end) {
                jsonError('Invalid input', 400);
            }

            $globalRate = $this->getGlobalAdminRate();
            $totalPay = $hours * $globalRate;

            $stmt = $this->pdo->prepare("SELECT id FROM attendance_admin_pay WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $start, ':end' => $end]);
            $existing = $stmt->fetch();

            if ($existing) {
                $update = $this->pdo->prepare("UPDATE attendance_admin_pay SET admin_hours = :hours, total_pay = :pay, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute([':hours' => $hours, ':pay' => $totalPay, ':id' => $existing['id']]);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_admin_pay (employee_id, period_start, period_end, admin_hours, total_pay) VALUES (:id, :start, :end, :hours, :pay)");
                $insert->execute([':id' => $employeeId, ':start' => $start, ':end' => $end, ':hours' => $hours, ':pay' => $totalPay]);
            }
            echo json_encode(['success' => true, 'total_pay' => $totalPay]);
        }
    }

    private function getGlobalAdminRate(): float {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key = 'global_admin_rate'");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (float)$row['value'] : 70.0;
    }

    private function setGlobalAdminRate(float $rate): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (key, value) VALUES ('global_admin_rate', :rate)
            ON CONFLICT (key) DO UPDATE SET value = :rate, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([':rate' => $rate]);
    }

    private function fetchSyncedFacultyHours(string $dtrTable, string $employeeId, string $periodStart, string $periodEnd): array {
        $allowedDtrTables = ['attendance_shs_dtr', 'attendance_college_dtr'];
        if (!in_array($dtrTable, $allowedDtrTables, true)) {
            throw new InvalidArgumentException('Invalid DTR table');
        }

        $this->ensureFacultySourceTables();

        $dtrStmt = $this->pdo->prepare("
            SELECT total_hours
            FROM {$dtrTable}
            WHERE employee_id = :id AND period_start = :start AND period_end = :end
            LIMIT 1
        ");
        $dtrStmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
        $regularHours = (float)($dtrStmt->fetchColumn() ?: 0);

        $adminStmt = $this->pdo->prepare("
            SELECT admin_hours, total_pay
            FROM attendance_admin_pay
            WHERE employee_id = :id AND period_start = :start AND period_end = :end
            LIMIT 1
        ");
        $adminStmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
        $admin = $adminStmt->fetch() ?: [];

        return [
            'regular_hours' => round($regularHours, 2),
            'admin_hours' => round((float)($admin['admin_hours'] ?? 0), 2),
            'admin_total_pay' => round((float)($admin['total_pay'] ?? 0), 2)
        ];
    }

    private function fetchSyncedSHS(string $empId, string $start, string $end): array {
        return $this->fetchSyncedFacultyHours('attendance_shs_dtr', $empId, $start, $end);
    }

    private function fetchSyncedCollege(string $empId, string $start, string $end): array {
        return $this->fetchSyncedFacultyHours('attendance_college_dtr', $empId, $start, $end);
    }

    private function ensureFacultySourceTables(): void {
        $this->pdo->exec("
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
        $this->pdo->exec("
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
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_college_dtr (
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
    }

    private function applySyncedFacultyPay(array $row, array $synced, float $regularRate): array {
        $grossPay = ($synced['regular_hours'] * $regularRate) + $synced['admin_total_pay'];
        $deductions =
            (float)($row['sss'] ?? 0) +
            (float)($row['philhealth'] ?? 0) +
            (float)($row['pagibig'] ?? 0) +
            (float)($row['withholding_tax'] ?? 0) +
            (float)($row['sss_loan'] ?? 0) +
            (float)($row['hdmf_loan'] ?? 0) +
            (float)($row['cash_advance'] ?? 0) +
            (float)($row['atm_deposit'] ?? 0);
        $netPay = $grossPay - $deductions + (float)($row['marketing_allowance'] ?? 0);

        $row['regular_hours'] = $synced['regular_hours'];
        $row['admin_hours'] = $synced['admin_hours'];
        $row['admin_total_pay'] = $synced['admin_total_pay'];
        $row['gross_pay'] = round($grossPay, 2);
        $row['net_pay'] = round(max($netPay, 0), 2);

        return $row;
    }

    // ============================================
    // ADMIN MASTER
    // ============================================

    private function handleAdminMaster(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_admin_master (
                id SERIAL PRIMARY KEY,
                employee_id VARCHAR(100) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                basic_pay DECIMAL(12,2) DEFAULT 0,
                overtime_pay DECIMAL(12,2) DEFAULT 0,
                gross DECIMAL(12,2) DEFAULT 0,
                sss DECIMAL(10,2) DEFAULT 0,
                philhealth DECIMAL(10,2) DEFAULT 0,
                pagibig DECIMAL(10,2) DEFAULT 0,
                withholding_tax DECIMAL(10,2) DEFAULT 0,
                sss_loan DECIMAL(10,2) DEFAULT 0,
                hdmf_loan DECIMAL(10,2) DEFAULT 0,
                cash_advance DECIMAL(10,2) DEFAULT 0,
                atm_deposit DECIMAL(10,2) DEFAULT 0,
                transpo_allowance DECIMAL(10,2) DEFAULT 0,
                marketing_allowance DECIMAL(10,2) DEFAULT 0,
                net_pay DECIMAL(12,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(employee_id, period_start, period_end)
            )
        ");

        $daysInPeriod = 16;
        $dailyRate = 650;
        $hourlyRate = $dailyRate / 8;

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if (!$periodStart || !$periodEnd) {
                jsonError('Missing period_start or period_end', 400);
            }

            $hasStatusColumn = $this->tableHasColumn('attendance_eda', 'status');
            $statusFilterSql = $hasStatusColumn ? " AND status = 'active'" : "";

            $stmt = $this->pdo->prepare("SELECT employee_id, lates, absences, overtime FROM attendance_eda WHERE period_start = :start AND period_end = :end {$statusFilterSql}");
            $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            $edaRows = $stmt->fetchAll();

            $response = [];
            foreach ($edaRows as $row) {
                $empId = $row['employee_id'];
                $basicPay = $dailyRate * $daysInPeriod;
                $overtimePay = $this->safeFloat($row['overtime']) * $hourlyRate * 1.25;
                $gross = $basicPay + $overtimePay;

                $s = $this->pdo->prepare("SELECT * FROM attendance_admin_master WHERE employee_id = :id AND period_start = :start AND period_end = :end");
                $s->execute([':id' => $empId, ':start' => $periodStart, ':end' => $periodEnd]);
                $saved = $s->fetch() ?: [];

                $response[] = [
                    'employee_id' => $empId,
                    'basic_pay' => round($basicPay, 2),
                    'overtime_pay' => round($overtimePay, 2),
                    'gross' => round($gross, 2),
                    'sss' => round((float)($saved['sss'] ?? 0), 2),
                    'philhealth' => round((float)($saved['philhealth'] ?? 0), 2),
                    'pagibig' => round((float)($saved['pagibig'] ?? 0), 2),
                    'wtax' => round((float)($saved['withholding_tax'] ?? 0), 2),
                    'sss_loan' => round((float)($saved['sss_loan'] ?? 0), 2),
                    'hdmf_loan' => round((float)($saved['hdmf_loan'] ?? 0), 2),
                    'cash_adv' => round((float)($saved['cash_advance'] ?? 0), 2),
                    'atm_dep' => round((float)($saved['atm_deposit'] ?? 0), 2),
                    'transpo' => round((float)($saved['transpo_allowance'] ?? 0), 2),
                    'marketing' => round((float)($saved['marketing_allowance'] ?? 0), 2),
                    'net_pay' => round((float)($saved['net_pay'] ?? $gross), 2)
                ];
            }
            echo json_encode($response);
        } elseif ($this->method === 'POST') {
            $employeeId = trim((string)($this->input['employee_id'] ?? ''));
            $periodStart = $this->input['period_start'] ?? '';
            $periodEnd = $this->input['period_end'] ?? '';

            if ($employeeId === '' || !$periodStart || !$periodEnd) {
                jsonError('Missing required fields', 400);
            }

            $sss = $this->safeFloat($this->input['sss'] ?? 0);
            $philhealth = $this->safeFloat($this->input['philhealth'] ?? 0);
            $pagibig = $this->safeFloat($this->input['pagibig'] ?? 0);
            $wtax = $this->safeFloat($this->input['wtax'] ?? 0);
            $sssLoan = $this->safeFloat($this->input['sss_loan'] ?? 0);
            $hdmfLoan = $this->safeFloat($this->input['hdmf_loan'] ?? 0);
            $cashAdv = $this->safeFloat($this->input['cash_adv'] ?? 0);
            $atmDep = $this->safeFloat($this->input['atm_dep'] ?? 0);
            $transpo = $this->safeFloat($this->input['transpo'] ?? 0);
            $marketing = $this->safeFloat($this->input['marketing'] ?? 0);

            $basicPay = $dailyRate * $daysInPeriod;
            $gross = $basicPay;
            $totalDeductions = $sss + $philhealth + $pagibig + $wtax + $sssLoan + $hdmfLoan + $cashAdv + $atmDep;
            $netPay = $gross - $totalDeductions + ($transpo + $marketing);
            if ($netPay < 0) $netPay = 0;

            $stmt = $this->pdo->prepare("
                INSERT INTO attendance_admin_master (employee_id, period_start, period_end, basic_pay, gross, sss, philhealth, pagibig, withholding_tax, sss_loan, hdmf_loan, cash_advance, atm_deposit, transpo_allowance, marketing_allowance, net_pay)
                VALUES (:id, :start, :end, :basic, :gross, :sss, :philhealth, :pagibig, :wtax, :sss_loan, :hdmf_loan, :cash_adv, :atm_dep, :transpo, :marketing, :net)
                ON CONFLICT (employee_id, period_start, period_end) DO UPDATE SET basic_pay = :basic, gross = :gross, sss = :sss, philhealth = :philhealth, pagibig = :pagibig, withholding_tax = :wtax, sss_loan = :sss_loan, hdmf_loan = :hdmf_loan, cash_advance = :cash_adv, atm_deposit = :atm_dep, transpo_allowance = :transpo, marketing_allowance = :marketing, net_pay = :net, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':basic' => $basicPay, ':gross' => $gross, ':sss' => $sss, ':philhealth' => $philhealth, ':pagibig' => $pagibig, ':wtax' => $wtax, ':sss_loan' => $sssLoan, ':hdmf_loan' => $hdmfLoan, ':cash_adv' => $cashAdv, ':atm_dep' => $atmDep, ':transpo' => $transpo, ':marketing' => $marketing, ':net' => $netPay]);
            echo json_encode(['success' => true, 'net_pay' => round($netPay, 2)]);
        }
    }

    // ============================================
    // SHS-DTR
    // ============================================

    private function handleSHSDTR(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_shs_dtr WHERE period_start = :start AND period_end = :end ORDER BY employee_id");
                $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_shs_dtr ORDER BY employee_id");
            }

            $results = $stmt->fetchAll();
            $safe = [];
            foreach ($results as $row) {
                $dailyData = json_decode($row['daily_data'] ?? '{}', true);
                $safe[] = [
                    'employee_id' => $row['employee_id'] ?? '',
                    'period_start' => $row['period_start'] ?? '',
                    'period_end' => $row['period_end'] ?? '',
                    'daily_data' => is_array($dailyData) ? $dailyData : [],
                    'total_hours' => (float)($row['total_hours'] ?? 0)
                ];
            }
            echo json_encode($safe);
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;
            $date = $this->input['date'] ?? null;

            $value = 0;
            if (isset($this->input['hours'])) {
                $value = (float)$this->input['hours'];
            } elseif (isset($this->input['present'])) {
                $value = (int)$this->input['present'];
            }

            if (!$employeeId || !$periodStart || !$periodEnd || !$date) {
                jsonError('Missing required fields', 400);
            }

            $stmt = $this->pdo->prepare("SELECT id, daily_data FROM attendance_shs_dtr WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
            $existing = $stmt->fetch();

            $dailyData = [];
            if ($existing) {
                $decoded = json_decode($existing['daily_data'], true);
                $dailyData = is_array($decoded) ? $decoded : [];
            }

            $dailyData[$date] = $value;
            $totalHours = array_sum($dailyData);

            if ($existing) {
                $update = $this->pdo->prepare("UPDATE attendance_shs_dtr SET daily_data = :data, total_hours = :total, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute([':data' => json_encode($dailyData), ':total' => $totalHours, ':id' => $existing['id']]);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_shs_dtr (employee_id, period_start, period_end, daily_data, total_hours) VALUES (:id, :start, :end, :data, :total)");
                $insert->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':data' => json_encode($dailyData), ':total' => $totalHours]);
            }
            echo json_encode(["success" => true, "total_hours" => $totalHours, "message" => "Data saved successfully"]);
        }
    }

    // ============================================
    // SHS-LOADING
    // ============================================

    private function handleSHSLoading(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_shs_loading WHERE period_start = :start AND period_end = :end");
                $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_shs_loading");
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
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                jsonError('Missing required fields', 400);
            }

            $stmt = $this->pdo->prepare("SELECT id FROM attendance_shs_loading WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
            $existing = $stmt->fetch();

            $data = [
                ':subject' => $this->input['subject'] ?? '',
                ':mon' => (float)($this->input['mon'] ?? 0),
                ':tue' => (float)($this->input['tue'] ?? 0),
                ':wed' => (float)($this->input['wed'] ?? 0),
                ':thu' => (float)($this->input['thu'] ?? 0),
                ':fri' => (float)($this->input['fri'] ?? 0),
                ':sat' => (float)($this->input['sat'] ?? 0),
                ':sun' => (float)($this->input['sun'] ?? 0)
            ];

            if ($existing) {
                $update = $this->pdo->prepare("UPDATE attendance_shs_loading SET subject = :subject, mon = :mon, tue = :tue, wed = :wed, thu = :thu, fri = :fri, sat = :sat, sun = :sun, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute(array_merge($data, [':id' => $existing['id']]));
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_shs_loading (employee_id, period_start, period_end, subject, mon, tue, wed, thu, fri, sat, sun) VALUES (:id, :start, :end, :subject, :mon, :tue, :wed, :thu, :fri, :sat, :sun)");
                $insert->execute(array_merge([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd], $data));
            }
            echo json_encode(["success" => true]);
        }
    }

    // ============================================
    // COLLEGE-DTR
    // ============================================

    private function handleCollegeDTR(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $start = $this->safeDate($_GET['period_start'] ?? null);
            $end = $this->safeDate($_GET['period_end'] ?? null);

            if ($start && $end) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_college_dtr WHERE period_start = :start AND period_end = :end ORDER BY employee_id");
                $stmt->execute([':start' => $start, ':end' => $end]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_college_dtr ORDER BY employee_id");
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
        } elseif ($this->method === 'POST') {
            $employeeId = trim($this->input['employee_id'] ?? '');
            $periodStart = $this->safeDate($this->input['period_start'] ?? null);
            $periodEnd = $this->safeDate($this->input['period_end'] ?? null);
            $date = $this->safeDate($this->input['date'] ?? null);

            $hours = null;
            if (isset($this->input['hours'])) {
                $hours = (float)$this->input['hours'];
            } elseif (isset($this->input['present'])) {
                $hours = ((int)$this->input['present'] === 1) ? 1.0 : 0.0;
            }

            if (!$employeeId || !$periodStart || !$periodEnd || !$date || $hours === null || $hours < 0) {
                jsonError('Invalid input', 400);
            }

            $stmt = $this->pdo->prepare("SELECT id, daily_data FROM attendance_college_dtr WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
            $existing = $stmt->fetch();

            $dailyData = $existing ? json_decode($existing['daily_data'], true) : [];
            if (!is_array($dailyData)) {
                $dailyData = [];
            }

            $dailyData[$date] = $hours;
            $totalHours = array_sum(array_map(static fn($value) => (float)$value, $dailyData));

            if ($existing) {
                $update = $this->pdo->prepare("UPDATE attendance_college_dtr SET daily_data = :data, total_hours = :hours, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute([':data' => json_encode($dailyData), ':hours' => $totalHours, ':id' => $existing['id']]);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_college_dtr (employee_id, period_start, period_end, daily_data, total_hours) VALUES (:id, :start, :end, :data, :hours)");
                $insert->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':data' => json_encode($dailyData), ':hours' => $totalHours]);
            }
            echo json_encode(['success' => true, 'total_hours' => $totalHours]);
        }
    }

    // ============================================
    // COLLEGE-LOADING
    // ============================================

    private function handleCollegeLoading(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_college_loading WHERE period_start = :start AND period_end = :end");
                $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_college_loading");
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
            echo json_encode(['success' => true, 'data' => $safe]);
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                jsonError('Missing required fields', 400);
            }

            $stmt = $this->pdo->prepare("SELECT id FROM attendance_college_loading WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
            $existing = $stmt->fetch();

            $data = [
                ':subject' => trim((string)($this->input['subject'] ?? '')),
                ':mon' => (float)($this->input['mon'] ?? 0),
                ':tue' => (float)($this->input['tue'] ?? 0),
                ':wed' => (float)($this->input['wed'] ?? 0),
                ':thu' => (float)($this->input['thu'] ?? 0),
                ':fri' => (float)($this->input['fri'] ?? 0),
                ':sat' => (float)($this->input['sat'] ?? 0),
                ':sun' => (float)($this->input['sun'] ?? 0)
            ];

            if ($existing) {
                $data[':id'] = $existing['id'];
                $update = $this->pdo->prepare("UPDATE attendance_college_loading SET subject = :subject, mon = :mon, tue = :tue, wed = :wed, thu = :thu, fri = :fri, sat = :sat, sun = :sun, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute($data);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_college_loading (employee_id, period_start, period_end, subject, mon, tue, wed, thu, fri, sat, sun) VALUES (:id, :start, :end, :subject, :mon, :tue, :wed, :thu, :fri, :sat, :sun)");
                $insert->execute(array_merge([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd], $data));
            }
            echo json_encode(['success' => true]);
        }
    }

    // ============================================
    // FACULTY-SHS
    // ============================================

    private function handleFacultySHS(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_faculty_shs WHERE period_start = :start AND period_end = :end");
                $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_faculty_shs");
            }

            $rows = $stmt->fetchAll();
            $data = array_map(function($row) {
                $synced = $this->fetchSyncedSHS(
                    (string)$row['employee_id'],
                    (string)$row['period_start'],
                    (string)$row['period_end']
                );
                return $this->applySyncedFacultyPay($row, $synced, 80);
            }, $rows);

            echo json_encode(['success' => true, 'data' => $data]);
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                jsonError('Missing required fields', 400);
            }

            $synced = $this->fetchSyncedSHS((string)$employeeId, (string)$periodStart, (string)$periodEnd);
            $regularHours = $synced['regular_hours'];
            $adminHours = $synced['admin_hours'];
            $sss = (float)($this->input['sss'] ?? 0);
            $philhealth = (float)($this->input['philhealth'] ?? 0);
            $pagibig = (float)($this->input['pagibig'] ?? 0);
            $wtax = (float)($this->input['withholding_tax'] ?? $this->input['wtax'] ?? 0);
            $sssLoan = (float)($this->input['sss_loan'] ?? 0);
            $hdmfLoan = (float)($this->input['hdmf_loan'] ?? 0);
            $cashAdvance = (float)($this->input['cash_advance'] ?? $this->input['cash_adv'] ?? 0);
            $atmDeposit = (float)($this->input['atm_deposit'] ?? $this->input['atm_dep'] ?? 0);
            $marketingAllowance = (float)($this->input['marketing_allowance'] ?? $this->input['marketing'] ?? 0);

            $grossPay = ($regularHours * 80) + $synced['admin_total_pay'];
            $deductions = $sss + $philhealth + $pagibig + $wtax + $sssLoan + $hdmfLoan + $cashAdvance + $atmDeposit;
            $netPay = $grossPay - $deductions + $marketingAllowance;
            if ($netPay < 0) $netPay = 0;

            $stmt = $this->pdo->prepare("SELECT id FROM attendance_faculty_shs WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
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
                $update = $this->pdo->prepare("UPDATE attendance_faculty_shs SET regular_hours = :regular_hours, admin_hours = :admin_hours, gross_pay = :gross_pay, sss = :sss, philhealth = :philhealth, pagibig = :pagibig, withholding_tax = :withholding_tax, sss_loan = :sss_loan, hdmf_loan = :hdmf_loan, cash_advance = :cash_advance, atm_deposit = :atm_deposit, marketing_allowance = :marketing_allowance, net_pay = :net_pay, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute($data);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_faculty_shs (employee_id, period_start, period_end, regular_hours, admin_hours, gross_pay, sss, philhealth, pagibig, withholding_tax, sss_loan, hdmf_loan, cash_advance, atm_deposit, marketing_allowance, net_pay) VALUES (:id, :start, :end, :regular_hours, :admin_hours, :gross_pay, :sss, :philhealth, :pagibig, :withholding_tax, :sss_loan, :hdmf_loan, :cash_advance, :atm_deposit, :marketing_allowance, :net_pay)");
                $insert->execute(array_merge([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd], $data));
            }
            echo json_encode(['success' => true, 'gross_pay' => $grossPay, 'net_pay' => $netPay, 'regular_hours' => $regularHours, 'admin_hours' => $adminHours, 'admin_total_pay' => $synced['admin_total_pay']]);
        }
    }

    // ============================================
    // FACULTY-COLLEGE
    // ============================================

    private function handleFacultyCollege(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_faculty_college (
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if ($periodStart && $periodEnd) {
                $stmt = $this->pdo->prepare("SELECT * FROM attendance_faculty_college WHERE period_start = :start AND period_end = :end");
                $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM attendance_faculty_college");
            }

            $rows = $stmt->fetchAll();
            $data = array_map(function($row) {
                $synced = $this->fetchSyncedCollege(
                    (string)$row['employee_id'],
                    (string)$row['period_start'],
                    (string)$row['period_end']
                );
                return $this->applySyncedFacultyPay($row, $synced, 85);
            }, $rows);

            echo json_encode(['success' => true, 'data' => $data]);
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                jsonError('Missing required fields', 400);
            }

            $synced = $this->fetchSyncedCollege((string)$employeeId, (string)$periodStart, (string)$periodEnd);
            $regularHours = $synced['regular_hours'];
            $adminHours = $synced['admin_hours'];
            $sss = (float)($this->input['sss'] ?? 0);
            $philhealth = (float)($this->input['philhealth'] ?? 0);
            $pagibig = (float)($this->input['pagibig'] ?? 0);
            $wtax = (float)($this->input['withholding_tax'] ?? $this->input['wtax'] ?? 0);
            $sssLoan = (float)($this->input['sss_loan'] ?? 0);
            $hdmfLoan = (float)($this->input['hdmf_loan'] ?? 0);
            $cashAdvance = (float)($this->input['cash_advance'] ?? $this->input['cash_adv'] ?? 0);
            $atmDeposit = (float)($this->input['atm_deposit'] ?? $this->input['atm_dep'] ?? 0);
            $marketingAllowance = (float)($this->input['marketing_allowance'] ?? $this->input['marketing'] ?? 0);

            $grossPay = ($regularHours * 85) + $synced['admin_total_pay'];
            $deductions = $sss + $philhealth + $pagibig + $wtax + $sssLoan + $hdmfLoan + $cashAdvance + $atmDeposit;
            $netPay = $grossPay - $deductions + $marketingAllowance;
            if ($netPay < 0) $netPay = 0;

            $stmt = $this->pdo->prepare("SELECT id FROM attendance_faculty_college WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
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
                $update = $this->pdo->prepare("UPDATE attendance_faculty_college SET regular_hours = :regular_hours, admin_hours = :admin_hours, gross_pay = :gross_pay, sss = :sss, philhealth = :philhealth, pagibig = :pagibig, withholding_tax = :withholding_tax, sss_loan = :sss_loan, hdmf_loan = :hdmf_loan, cash_advance = :cash_advance, atm_deposit = :atm_deposit, marketing_allowance = :marketing_allowance, net_pay = :net_pay, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute($data);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_faculty_college (employee_id, period_start, period_end, regular_hours, admin_hours, gross_pay, sss, philhealth, pagibig, withholding_tax, sss_loan, hdmf_loan, cash_advance, atm_deposit, marketing_allowance, net_pay) VALUES (:id, :start, :end, :regular_hours, :admin_hours, :gross_pay, :sss, :philhealth, :pagibig, :withholding_tax, :sss_loan, :hdmf_loan, :cash_advance, :atm_deposit, :marketing_allowance, :net_pay)");
                $insert->execute(array_merge([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd], $data));
            }
            echo json_encode(['success' => true, 'gross_pay' => $grossPay, 'net_pay' => $netPay, 'regular_hours' => $regularHours, 'admin_hours' => $adminHours, 'admin_total_pay' => $synced['admin_total_pay']]);
        }
    }

    // ============================================
    // GUARD
    // ============================================

    private function handleGuard(): void {
        $this->pdo->exec("
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            try {
                if ($periodStart && $periodEnd) {
                    $stmt = $this->pdo->prepare("SELECT * FROM attendance_guard WHERE period_start = :start AND period_end = :end");
                    $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
                } else {
                    $stmt = $this->pdo->query("SELECT * FROM attendance_guard");
                }

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!is_array($results)) $results = [];

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
                echo json_encode($transformed);
            } catch (Exception $e) {
                echo json_encode([]);
            }
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                echo json_encode(["success" => false]);
                return;
            }

            if (isset($this->input['rate'])) {
                $stmt = $this->pdo->prepare("SELECT id FROM attendance_guard WHERE employee_id = :id AND period_start = :start AND period_end = :end");
                $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $update = $this->pdo->prepare("UPDATE attendance_guard SET rate = :rate, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $update->execute([':rate' => $this->input['rate'], ':id' => $existing['id']]);
                } else {
                    $insert = $this->pdo->prepare("INSERT INTO attendance_guard (employee_id, period_start, period_end, rate) VALUES (:id, :start, :end, :rate)");
                    $insert->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':rate' => $this->input['rate']]);
                }
                echo json_encode(["success" => true]);
                return;
            }

            $date = $this->input['date'] ?? null;
            $present = $this->input['present'] ?? 0;

            if (!$date) {
                echo json_encode(["success" => false]);
                return;
            }

            $stmt = $this->pdo->prepare("SELECT id, daily_data, rate FROM attendance_guard WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $dailyData = [];
            if ($existing && !empty($existing['daily_data'])) {
                $decoded = json_decode($existing['daily_data'], true);
                if (is_array($decoded)) $dailyData = $decoded;
            }

            $rate = $existing['rate'] ?? 0;
            $dailyData[$date] = $present == 1;
            $daysWorked = count(array_filter($dailyData));
            $totalPay = $daysWorked * $rate;

            if ($existing) {
                $update = $this->pdo->prepare("UPDATE attendance_guard SET daily_data = :data, days_worked = :days, total_pay = :pay, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute([':data' => json_encode($dailyData), ':days' => $daysWorked, ':pay' => $totalPay, ':id' => $existing['id']]);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_guard (employee_id, period_start, period_end, daily_data, days_worked, total_pay) VALUES (:id, :start, :end, :data, :days, :pay)");
                $insert->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':data' => json_encode($dailyData), ':days' => $daysWorked, ':pay' => $totalPay]);
            }
            echo json_encode(["success" => true]);
        }
    }

    // ============================================
    // SA - Student Assistants
    // ============================================

    private function handleSA(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_sa (
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

        if ($this->method === 'GET') {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            try {
                if ($periodStart && $periodEnd) {
                    $stmt = $this->pdo->prepare("SELECT * FROM attendance_sa WHERE period_start = :start AND period_end = :end");
                    $stmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
                } else {
                    $stmt = $this->pdo->query("SELECT * FROM attendance_sa");
                }

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!is_array($results)) $results = [];

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
                echo json_encode($transformed);
            } catch (Exception $e) {
                echo json_encode([]);
            }
        } elseif ($this->method === 'POST') {
            $employeeId = $this->input['employee_id'] ?? null;
            $periodStart = $this->input['period_start'] ?? null;
            $periodEnd = $this->input['period_end'] ?? null;

            if (!$employeeId || !$periodStart || !$periodEnd) {
                echo json_encode(["success" => false]);
                return;
            }

            if (isset($this->input['rate'])) {
                $stmt = $this->pdo->prepare("SELECT id FROM attendance_sa WHERE employee_id = :id AND period_start = :start AND period_end = :end");
                $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $update = $this->pdo->prepare("UPDATE attendance_sa SET rate = :rate, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $update->execute([':rate' => $this->input['rate'], ':id' => $existing['id']]);
                } else {
                    $insert = $this->pdo->prepare("INSERT INTO attendance_sa (employee_id, period_start, period_end, rate) VALUES (:id, :start, :end, :rate)");
                    $insert->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':rate' => $this->input['rate']]);
                }
                echo json_encode(["success" => true]);
                return;
            }

            $date = $this->input['date'] ?? null;
            $present = $this->input['present'] ?? 0;

            if (!$date) {
                echo json_encode(["success" => false]);
                return;
            }

            $stmt = $this->pdo->prepare("SELECT id, daily_data, rate FROM attendance_sa WHERE employee_id = :id AND period_start = :start AND period_end = :end");
            $stmt->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $dailyData = [];
            if ($existing && !empty($existing['daily_data'])) {
                $decoded = json_decode($existing['daily_data'], true);
                if (is_array($decoded)) $dailyData = $decoded;
            }

            $rate = $existing['rate'] ?? 0;
            $dailyData[$date] = $present == 1;
            $daysWorked = count(array_filter($dailyData));
            $totalPay = $daysWorked * $rate;

            if ($existing) {
                $update = $this->pdo->prepare("UPDATE attendance_sa SET daily_data = :data, days_worked = :days, total_pay = :pay, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute([':data' => json_encode($dailyData), ':days' => $daysWorked, ':pay' => $totalPay, ':id' => $existing['id']]);
            } else {
                $insert = $this->pdo->prepare("INSERT INTO attendance_sa (employee_id, period_start, period_end, daily_data, days_worked, total_pay) VALUES (:id, :start, :end, :data, :days, :pay)");
                $insert->execute([':id' => $employeeId, ':start' => $periodStart, ':end' => $periodEnd, ':data' => json_encode($dailyData), ':days' => $daysWorked, ':pay' => $totalPay]);
            }
            echo json_encode(["success" => true]);
        }
    }
}

// ============================================
// EXECUTE UNIFIED ROUTER
// ============================================
try {
    $router = new AttendanceRouter($pdo, $type, $method, $input);
    $router->route();
} catch (Throwable $e) {
    jsonError('Server error', 500, $e->getMessage());
}
