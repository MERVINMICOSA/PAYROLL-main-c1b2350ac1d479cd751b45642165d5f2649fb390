<?php

/**
 * ADMIN MASTER ATTENDANCE API (HARDENED)
 */

require_once __DIR__ . '/../_bootstrap.php';
bootstrapStartSession();
bootstrapRequireAuth();

/* ---------------- DB CONNECTION ---------------- */
try {
    $pdo = bootstrapGetPdo('require');

    /* ---------------- TABLE ---------------- */
    $pdo->exec("
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

    /* ---------------- CONSTANTS ---------------- */
    $daysInPeriod = 16;
    $dailyRate = 650;
    $hourlyRate = $dailyRate / 8;

    /* ---------------- HELPERS ---------------- */
    function safeFloat($v): float {
        return is_numeric($v) ? (float)$v : 0.0;
    }

    function tableHasColumn(PDO $pdo, string $tableName, string $columnName): bool {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_name = :table
              AND column_name = :column
            LIMIT 1
        ");
        $stmt->execute([
            ':table' => $tableName,
            ':column' => $columnName
        ]);
        return (bool)$stmt->fetchColumn();
    }

    /* ================= GET ================= */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $periodStart = $_GET['period_start'] ?? null;
        $periodEnd = $_GET['period_end'] ?? null;

        if (!$periodStart || !$periodEnd) {
            jsonError('Missing period_start or period_end', 400);
        }

        $hasStatusColumn = tableHasColumn($pdo, 'attendance_eda', 'status');
        $statusFilterSql = $hasStatusColumn ? " AND status = 'active'" : "";

        // Get EDA source (supports both old and new attendance_eda schemas)
        $stmt = $pdo->prepare("
            SELECT employee_id, lates, absences, overtime
            FROM attendance_eda
            WHERE period_start = :start
              AND period_end = :end
              {$statusFilterSql}
        ");
        $stmt->execute([
            ':start' => $periodStart,
            ':end' => $periodEnd
        ]);

        $edaRows = $stmt->fetchAll();

        $response = [];

        foreach ($edaRows as $row) {

            $empId = $row['employee_id'];

            $basicPay = $dailyRate * $daysInPeriod;
            $overtimePay = safeFloat($row['overtime']) * $hourlyRate * 1.25;
            $gross = $basicPay + $overtimePay;

            // existing saved deductions
            $s = $pdo->prepare("
                SELECT *
                FROM attendance_admin_master
                WHERE employee_id = :id
                  AND period_start = :start
                  AND period_end = :end
            ");

            $s->execute([
                ':id' => $empId,
                ':start' => $periodStart,
                ':end' => $periodEnd
            ]);

            $saved = $s->fetch() ?: [];

            $response[] = [
                'employee_id' => $empId,
                'basic_pay' => round($basicPay, 2),
                'overtime_pay' => round($overtimePay, 2),
                'gross' => round($gross, 2),

                'sss' => round($saved['sss'] ?? 0, 2),
                'philhealth' => round($saved['philhealth'] ?? 0, 2),
                'pagibig' => round($saved['pagibig'] ?? 0, 2),
                'wtax' => round($saved['withholding_tax'] ?? 0, 2),

                'sss_loan' => round($saved['sss_loan'] ?? 0, 2),
                'hdmf_loan' => round($saved['hdmf_loan'] ?? 0, 2),
                'cash_adv' => round($saved['cash_advance'] ?? 0, 2),
                'atm_dep' => round($saved['atm_deposit'] ?? 0, 2),

                'transpo' => round($saved['transpo_allowance'] ?? 0, 2),
                'marketing' => round($saved['marketing_allowance'] ?? 0, 2),

                'net_pay' => round($saved['net_pay'] ?? $gross, 2)
            ];
        }

        echo json_encode($response);
        exit;
    }

    /* ================= POST ================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $input = json_decode(file_get_contents("php://input"), true);

        if (!is_array($input)) {
            jsonError('Invalid JSON body', 400);
        }

        $employeeId = trim((string)($input['employee_id'] ?? ''));
        $periodStart = $input['period_start'] ?? '';
        $periodEnd = $input['period_end'] ?? '';

        if ($employeeId === '' || !$periodStart || !$periodEnd) {
            jsonError('Missing required fields', 400);
        }

        // sanitize inputs
        $sss = safeFloat($input['sss'] ?? 0);
        $philhealth = safeFloat($input['philhealth'] ?? 0);
        $pagibig = safeFloat($input['pagibig'] ?? 0);
        $wtax = safeFloat($input['wtax'] ?? 0);

        $sssLoan = safeFloat($input['sss_loan'] ?? 0);
        $hdmfLoan = safeFloat($input['hdmf_loan'] ?? 0);
        $cashAdv = safeFloat($input['cash_adv'] ?? 0);
        $atmDep = safeFloat($input['atm_dep'] ?? 0);

        $transpo = safeFloat($input['transpo'] ?? 0);
        $marketing = safeFloat($input['marketing'] ?? 0);

        // base pay
        $basicPay = $dailyRate * $daysInPeriod;
        $overtimePay = 0;
        $gross = $basicPay;

        $totalDeductions =
            $sss + $philhealth + $pagibig + $wtax +
            $sssLoan + $hdmfLoan + $cashAdv + $atmDep;

        $netPay = $gross - $totalDeductions + ($transpo + $marketing);

        // UPSERT
        $stmt = $pdo->prepare("
            INSERT INTO attendance_admin_master (
                employee_id, period_start, period_end,
                basic_pay, overtime_pay, gross,
                sss, philhealth, pagibig, withholding_tax,
                sss_loan, hdmf_loan, cash_advance, atm_deposit,
                transpo_allowance, marketing_allowance, net_pay
            ) VALUES (
                :id, :start, :end,
                :basic, :ot, :gross,
                :sss, :philhealth, :pagibig, :wtax,
                :sss_loan, :hdmf_loan, :cash_adv, :atm_dep,
                :transpo, :marketing, :net
            )
            ON CONFLICT (employee_id, period_start, period_end)
            DO UPDATE SET
                basic_pay = EXCLUDED.basic_pay,
                overtime_pay = EXCLUDED.overtime_pay,
                gross = EXCLUDED.gross,
                sss = EXCLUDED.sss,
                philhealth = EXCLUDED.philhealth,
                pagibig = EXCLUDED.pagibig,
                withholding_tax = EXCLUDED.withholding_tax,
                sss_loan = EXCLUDED.sss_loan,
                hdmf_loan = EXCLUDED.hdmf_loan,
                cash_advance = EXCLUDED.cash_advance,
                atm_deposit = EXCLUDED.atm_deposit,
                transpo_allowance = EXCLUDED.transpo_allowance,
                marketing_allowance = EXCLUDED.marketing_allowance,
                net_pay = EXCLUDED.net_pay,
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':id' => $employeeId,
            ':start' => $periodStart,
            ':end' => $periodEnd,

            ':basic' => $basicPay,
            ':ot' => $overtimePay,
            ':gross' => $gross,

            ':sss' => $sss,
            ':philhealth' => $philhealth,
            ':pagibig' => $pagibig,
            ':wtax' => $wtax,

            ':sss_loan' => $sssLoan,
            ':hdmf_loan' => $hdmfLoan,
            ':cash_adv' => $cashAdv,
            ':atm_dep' => $atmDep,

            ':transpo' => $transpo,
            ':marketing' => $marketing,
            ':net' => $netPay
        ]);

        echo json_encode([
            'success' => true,
            'net_pay' => round($netPay, 2)
        ]);
        exit;
    }

    jsonError('Method not allowed', 405);

} catch (Throwable $e) {
    jsonError('Server error', 500, $e->getMessage());
}