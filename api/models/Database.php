<?php
// ============================================
// UNIFIED DATABASE CLASS
// ============================================
// PostgreSQL-only, uses DatabaseConfig singleton.
// Combines generic query helpers + all model methods.
// Sanitizes inputs via Sanitizer class.
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/sanitize.php';

class Database {
    private $conn;

    public function __construct() {
        $this->conn = DatabaseConfig::getInstance();
        if (!($this->conn instanceof PDO)) {
            throw new Exception('Database connection must be a PDO instance');
        }
    }

    // ============================================
    // GENERIC QUERY HELPERS
    // ============================================

    private function executeQuery(string $sql, array $params = []): PDOStatement {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function query(string $sql, array $params = []): PDOStatement {
        return $this->executeQuery($sql, $params);
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $results = $this->fetchAll($sql, $params);
        return $results[0] ?? null;
    }

    public function insert(string $table, array $data): int {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $stmt = $this->executeQuery($sql, array_values($data));
        return (int) $stmt->fetchColumn();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): bool {
        $set = [];
        $params = [];
        foreach ($data as $field => $value) {
            $set[] = "{$field} = ?";
            $params[] = $value;
        }
        $params = array_merge($params, $whereParams);
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        $this->executeQuery($sql, $params);
        return true;
    }

    public function delete(string $table, string $where, array $whereParams = []): bool {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->executeQuery($sql, $whereParams);
        return true;
    }

    // ============================================
    // USER METHODS
    // ============================================

    public function getUserByUsername(string $username): ?array {
        $sql = "SELECT * FROM users WHERE username = ? AND status = 'Active'";
        return $this->fetchOne($sql, [Sanitizer::sanitize($username)]);
    }

    public function getUserById(int $id): ?array {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->fetchOne($sql, [Sanitizer::sanitizeInt($id)]);
    }

    public function getAllUsers(): array {
        $sql = "SELECT id, username, full_name, email, phone, role, status, created_at FROM users ORDER BY id";
        return $this->fetchAll($sql);
    }

    public function addUser(array $data): int {
        return $this->insert('users', [
            'username'     => Sanitizer::sanitize($data['username']),
            'password_hash'=> Sanitizer::sanitize($data['password_hash']),
            'full_name'    => Sanitizer::sanitize($data['full_name']),
            'email'        => Sanitizer::sanitizeEmail($data['email'] ?? ''),
            'phone'        => Sanitizer::sanitizePhone($data['phone'] ?? ''),
            'role'         => Sanitizer::sanitize($data['role'] ?? 'teacher'),
            'status'       => Sanitizer::sanitize($data['status'] ?? 'Active')
        ]);
    }

    public function updateUser(array $data): bool {
        $sanitized = [
            'full_name' => Sanitizer::sanitize($data['full_name']),
            'email'     => Sanitizer::sanitizeEmail($data['email'] ?? ''),
            'phone'     => Sanitizer::sanitizePhone($data['phone'] ?? ''),
            'role'      => Sanitizer::sanitize($data['role']),
            'status'    => Sanitizer::sanitize($data['status'])
        ];
        return $this->update('users', $sanitized, 'id = ?', [Sanitizer::sanitizeInt($data['id'])]);
    }

    public function deleteUser(int $id): bool {
        return $this->delete('users', 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    // ============================================
    // SESSION METHODS
    // ============================================

    public function createSession(int $userId, string $token, string $expiresAt, ?string $ipAddress = null, ?string $userAgent = null): bool {
        $sql = "INSERT INTO sessions (user_id, token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $this->executeQuery($sql, [
            Sanitizer::sanitizeInt($userId),
            Sanitizer::sanitize($token),
            $expiresAt,
            Sanitizer::sanitize($ipAddress),
            Sanitizer::sanitize($userAgent)
        ]);
        return true;
    }

    public function validateToken(string $token): ?array {
        $sql = "SELECT s.*, u.username, u.full_name, u.role, u.email
                FROM sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.token = ? AND s.expires_at > CURRENT_TIMESTAMP AND u.status = 'Active'";
        return $this->fetchOne($sql, [Sanitizer::sanitize($token)]);
    }

    public function deleteSession(string $token): bool {
        return $this->delete('sessions', 'token = ?', [Sanitizer::sanitize($token)]);
    }

    // ============================================
    // EMPLOYEE METHODS
    // ============================================

    public function getAllEmployees(): array {
        $sql = "SELECT * FROM employees ORDER BY id";
        return $this->fetchAll($sql);
    }

    public function getEmployeeById(int $id): ?array {
        $sql = "SELECT * FROM employees WHERE id = ?";
        return $this->fetchOne($sql, [Sanitizer::sanitizeInt($id)]);
    }

    public function addEmployee(array $data): int {
        $sanitized = [
            'full_name'       => Sanitizer::sanitize($data['full_name']),
            'position'        => Sanitizer::sanitize($data['position'] ?? ''),
            'department'      => Sanitizer::sanitize($data['department'] ?? ''),
            'employment_type' => Sanitizer::sanitize($data['employment_type'] ?? 'Regular'),
            'base_salary'     => Sanitizer::sanitizeFloat($data['base_salary'] ?? 0),
            'hourly_rate'     => Sanitizer::sanitizeFloat($data['hourly_rate'] ?? 0),
            'admin_pay_rate'  => Sanitizer::sanitizeFloat($data['admin_pay_rate'] ?? 0),
            'email'           => Sanitizer::sanitizeEmail($data['email'] ?? ''),
            'phone'           => Sanitizer::sanitizePhone($data['phone'] ?? ''),
            'hire_date'       => $data['hire_date'] ?? null,
            'assignment'      => Sanitizer::sanitize($data['assignment'] ?? 'regular'),
            'rate_shs'        => Sanitizer::sanitizeFloat($data['rate_shs'] ?? 80),
            'rate_college'    => Sanitizer::sanitizeFloat($data['rate_college'] ?? 85),
            'rate_admin'      => Sanitizer::sanitizeFloat($data['rate_admin'] ?? 70),
            'rate_guard'      => Sanitizer::sanitizeFloat($data['rate_guard'] ?? 433),
            'rate_sa'         => Sanitizer::sanitizeFloat($data['rate_sa'] ?? 100),
            'status'          => Sanitizer::sanitize($data['status'] ?? 'Active')
        ];
        return $this->insert('employees', $sanitized);
    }

    public function updateEmployee(int $id, array $data): bool {
        $sanitized = [];
        $allowedFields = [
            'full_name', 'position', 'department', 'employment_type',
            'email', 'phone', 'status', 'birth_date', 'hire_date',
            'sss', 'philhealth', 'pagibig', 'tin',
            'emergency_name', 'emergency_relation', 'emergency_phone',
            'assignment'
        ];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = Sanitizer::sanitize($data[$field]);
            }
        }
        if (isset($data['base_salary']))     $sanitized['base_salary']    = Sanitizer::sanitizeFloat($data['base_salary']);
        if (isset($data['hourly_rate']))     $sanitized['hourly_rate']    = Sanitizer::sanitizeFloat($data['hourly_rate']);
        if (isset($data['admin_pay_rate']))  $sanitized['admin_pay_rate'] = Sanitizer::sanitizeFloat($data['admin_pay_rate']);
        if (isset($data['rate_shs']))        $sanitized['rate_shs']       = Sanitizer::sanitizeFloat($data['rate_shs']);
        if (isset($data['rate_college']))    $sanitized['rate_college']   = Sanitizer::sanitizeFloat($data['rate_college']);
        if (isset($data['rate_admin']))      $sanitized['rate_admin']     = Sanitizer::sanitizeFloat($data['rate_admin']);
        if (isset($data['rate_guard']))      $sanitized['rate_guard']     = Sanitizer::sanitizeFloat($data['rate_guard']);
        if (isset($data['rate_sa']))         $sanitized['rate_sa']        = Sanitizer::sanitizeFloat($data['rate_sa']);

        return $this->update('employees', $sanitized, 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    public function deleteEmployee(int $id): bool {
        return $this->delete('employees', 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    // ============================================
    // ATTENDANCE METHODS
    // ============================================

    public function getAllAttendance(): array {
        $sql = "SELECT * FROM attendance ORDER BY id DESC";
        return $this->fetchAll($sql);
    }

    public function getAttendanceByEmployee(int $employeeId): array {
        $sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC";
        return $this->fetchAll($sql, [Sanitizer::sanitizeInt($employeeId)]);
    }

    public function addAttendance(array $data): int {
        $sanitized = [
            'employee_id'     => Sanitizer::sanitizeInt($data['employee_id']),
            'tab_type'        => Sanitizer::sanitize($data['tab_type'] ?? 'eda'),
            'date'            => $data['date'],
            'period_start'    => $data['period_start'] ?? null,
            'period_end'      => $data['period_end'] ?? null,
            'payroll_period'  => Sanitizer::sanitize($data['payroll_period'] ?? ''),
            'mon'             => Sanitizer::sanitizeFloat($data['mon'] ?? 0),
            'tue'             => Sanitizer::sanitizeFloat($data['tue'] ?? 0),
            'wed'             => Sanitizer::sanitizeFloat($data['wed'] ?? 0),
            'thu'             => Sanitizer::sanitizeFloat($data['thu'] ?? 0),
            'fri'             => Sanitizer::sanitizeFloat($data['fri'] ?? 0),
            'sat'             => Sanitizer::sanitizeFloat($data['sat'] ?? 0),
            'sun'             => Sanitizer::sanitizeFloat($data['sun'] ?? 0),
            'hours_worked'    => Sanitizer::sanitizeFloat($data['hours_worked'] ?? 0),
            'overtime'        => Sanitizer::sanitizeFloat($data['overtime'] ?? 0),
            'lates'           => Sanitizer::sanitizeFloat($data['lates'] ?? 0),
            'absences'        => Sanitizer::sanitizeInt($data['absences'] ?? 0),
            'pay_type'        => Sanitizer::sanitize($data['pay_type'] ?? 'regular'),
            'admin_pay_rate'  => Sanitizer::sanitizeFloat($data['admin_pay_rate'] ?? 0),
            'notes'           => Sanitizer::sanitize($data['notes'] ?? '')
        ];
        return $this->insert('attendance', $sanitized);
    }

    public function updateAttendance(int $id, array $data): bool {
        $sanitized = [];
        $allowedFields = ['mon','tue','wed','thu','fri','sat','sun','hours_worked','overtime','lates','absences','pay_type','admin_pay_rate','notes','period_start','period_end','payroll_period'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = is_numeric($data[$field]) ? Sanitizer::sanitizeFloat($data[$field]) : Sanitizer::sanitize($data[$field]);
            }
        }
        return $this->update('attendance', $sanitized, 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    public function deleteAttendance(int $id): bool {
        return $this->delete('attendance', 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    // ============================================
    // PAYROLL METHODS
    // ============================================

    public function getAllPayroll(): array {
        $sql = "SELECT * FROM payroll ORDER BY id DESC";
        return $this->fetchAll($sql);
    }

    public function getPayrollById(int $id): ?array {
        $sql = "SELECT * FROM payroll WHERE id = ?";
        return $this->fetchOne($sql, [Sanitizer::sanitizeInt($id)]);
    }

    public function addPayroll(array $data): int {
        $sanitized = [
            'employee_id'       => Sanitizer::sanitizeInt($data['employee_id']),
            'period'            => Sanitizer::sanitize($data['period']),
            'period_start'      => $data['period_start'] ?? null,
            'period_end'        => $data['period_end'] ?? null,
            'regular_hours'     => Sanitizer::sanitizeFloat($data['regular_hours'] ?? 0),
            'overtime_hours'    => Sanitizer::sanitizeFloat($data['overtime_hours'] ?? 0),
            'admin_pay'         => Sanitizer::sanitizeFloat($data['admin_pay'] ?? 0),
            'gross_salary'      => Sanitizer::sanitizeFloat($data['gross_salary'] ?? 0),
            'sss'               => Sanitizer::sanitizeFloat($data['sss'] ?? 0),
            'philhealth'        => Sanitizer::sanitizeFloat($data['philhealth'] ?? 0),
            'pagibig'           => Sanitizer::sanitizeFloat($data['pagibig'] ?? 0),
            'withholding_tax'   => Sanitizer::sanitizeFloat($data['withholding_tax'] ?? 0),
            'undertime_deduction'=> Sanitizer::sanitizeFloat($data['undertime_deduction'] ?? 0),
            'total_deduction'   => Sanitizer::sanitizeFloat($data['total_deduction'] ?? 0),
            'net_salary'        => Sanitizer::sanitizeFloat($data['net_salary'] ?? 0),
            'status'            => Sanitizer::sanitize($data['status'] ?? 'Pending')
        ];
        return $this->insert('payroll', $sanitized);
    }

    public function updatePayroll(int $id, array $data): bool {
        $sanitized = [];
        $allowedFields = [
            'period','period_start','period_end','regular_hours','overtime_hours','admin_pay',
            'gross_salary','sss','philhealth','pagibig','withholding_tax','undertime_deduction',
            'sss_loan','hdmf_loan','cash_advance','atm_deposit','transpo_allowance','marketing_allowance',
            'total_deduction','net_salary','status','approved','approved_at','approved_by','rejection_reason'
        ];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = is_numeric($data[$field]) || is_null($data[$field]) ? $data[$field] : Sanitizer::sanitize($data[$field]);
            }
        }
        return $this->update('payroll', $sanitized, 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    public function updatePayrollStatus(int $id, string $status, ?string $reason = null, ?string $approvedBy = null): bool {
        $data = ['status' => Sanitizer::sanitize($status)];
        if ($status === 'Approved') {
            $data['approved'] = true;
            $data['approved_at'] = date('Y-m-d H:i:s');
            $data['approved_by'] = Sanitizer::sanitize($approvedBy);
        } elseif ($status === 'Rejected') {
            $data['approved'] = false;
            $data['rejection_reason'] = Sanitizer::sanitize($reason);
        }
        return $this->updatePayroll($id, $data);
    }

    public function deletePayroll(int $id): bool {
        return $this->delete('payroll', 'id = ?', [Sanitizer::sanitizeInt($id)]);
    }

    public function generatePayrollFromAttendance(string $period_start, string $period_end, string $period_display = 'Custom'): array {
        // Delete existing payroll for this exact period (regenerate fresh)
        $this->delete('payroll', 'period_start = ? AND period_end = ?', [$period_start, $period_end]);
        
        // Get employees for this period's attendance, with EDA summarized separately
        // so lates/absences/overtime are not multiplied by multiple attendance rows.
        $attendance = $this->fetchAll("
            SELECT a.employee_id, emp.full_name, emp.hourly_rate, emp.admin_pay_rate, emp.assignment,
                   a.total_hours,
                   a.total_ot,
                   a.admin_total,
                   COALESCE(eda.total_lates, 0) as total_lates,
                   COALESCE(eda.total_absences, 0) as total_absences,
                   COALESCE(eda.total_eda_ot, 0) as total_eda_ot
            FROM (
                SELECT employee_id, period_start, period_end,
                       SUM(COALESCE(hours_worked, 0)) as total_hours,
                       SUM(COALESCE(overtime, 0)) as total_ot,
                       SUM(COALESCE(admin_pay_rate, 0) * COALESCE(hours_worked, 0)) as admin_total
                FROM attendance
                WHERE period_start = ? AND period_end = ?
                GROUP BY employee_id, period_start, period_end
            ) a
            JOIN employees emp ON a.employee_id = emp.id
            LEFT JOIN (
                SELECT employee_id, period_start, period_end,
                       SUM(COALESCE(lates, 0)) as total_lates,
                       SUM(COALESCE(absences, 0)) as total_absences,
                       SUM(COALESCE(overtime, 0)) as total_eda_ot
                FROM attendance_eda
                WHERE period_start = ? AND period_end = ?
                GROUP BY employee_id, period_start, period_end
            ) eda ON a.employee_id::text = eda.employee_id
                AND a.period_start = eda.period_start
                AND a.period_end = eda.period_end
        ", [$period_start, $period_end, $period_start, $period_end]);
        
        $generated = [];
        foreach ($attendance as $emp) {
            $totalHours = (float)($emp['total_hours'] ?? 0);
            $hourlyRate = (float)($emp['hourly_rate'] ?? 0);
            $undertimeFromLates = ((float)($emp['total_lates'] ?? 0)) / 60;
            $undertimeFromAbsences = ((float)($emp['total_absences'] ?? 0)) * 8;
            $totalUndertime = $undertimeFromLates + $undertimeFromAbsences;
            $regularHours = max(0, $totalHours - $totalUndertime);
            $totalOvertime = (float)($emp['total_ot'] ?? 0) + (float)($emp['total_eda_ot'] ?? 0);

            $regular_pay = $regularHours * $hourlyRate;
            $ot_pay = $totalOvertime * $hourlyRate * 1.25; // 25% OT premium
            $admin_pay = $emp['admin_total'] ?? 0;
            $gross = $regular_pay + $ot_pay + $admin_pay;
            $undertimeDeduction = $totalUndertime * $hourlyRate;
            
            // Simple deductions (customize based on real rates)
            $sss = min($gross * 0.045, 900);
            $philhealth = min($gross * 0.03, 300);
            $pagibig = min($gross * 0.02, 100);
            $tax = max(0, $gross * 0.05 - 100); // Basic withholding
            $total_deductions = $sss + $philhealth + $pagibig + $tax;
            $net = $gross - $total_deductions;
            
            $payrollData = [
                'employee_id' => (int)$emp['employee_id'],
                'period' => $period_display,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'regular_hours' => (float)$regularHours,
                'overtime_hours' => (float)$totalOvertime,
                'admin_pay' => (float)$admin_pay,
                'gross_salary' => (float)$gross,
                'sss' => (float)$sss,
                'philhealth' => (float)$philhealth,
                'pagibig' => (float)$pagibig,
                'withholding_tax' => (float)$tax,
                'undertime_deduction' => (float)$undertimeDeduction,
                'total_deduction' => (float)$total_deductions,
                'net_salary' => (float)$net,
                'status' => 'Pending'
            ];
            
            $id = $this->addPayroll($payrollData);
            $generated[] = ['id' => $id, 'employee_name' => $emp['full_name'], 'net_salary' => $net];
        }
        
        return $generated;
    }

    // ============================================
    // ATTENDANCE PERIOD METHODS
    // ============================================

    public function getAllPeriods(): array {
        $sql = "SELECT * FROM attendance_periods ORDER BY period_start DESC";
        return $this->fetchAll($sql);
    }

    public function getActivePeriod(): ?array {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM attendance_periods 
                WHERE period_start <= ? AND period_end >= ? 
                ORDER BY period_start DESC LIMIT 1";
        return $this->fetchOne($sql, [$today, $today]);
    }

    public function getPeriodByDates(string $start, string $end): ?array {
        $sql = "SELECT * FROM attendance_periods WHERE period_start = ? AND period_end = ? LIMIT 1";
        return $this->fetchOne($sql, [$start, $end]);
    }

    public function getOverlappingPeriods(string $start, string $end, ?int $excludeId = null): array {
        $sql = "SELECT * FROM attendance_periods 
                WHERE NOT (period_end < ? OR period_start > ?)";
        $params = [$start, $end];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $sql .= " ORDER BY period_start";
        return $this->fetchAll($sql, $params);
    }

    public function createPeriod(string $start, string $end, ?string $description = null): int {
        // Check for overlaps
        $overlaps = $this->getOverlappingPeriods($start, $end);
        if (!empty($overlaps)) {
            throw new Exception("Period overlaps with existing period(s)");
        }

        // Check if exact period already exists
        $existing = $this->getPeriodByDates($start, $end);
        if ($existing) {
            throw new Exception("Period already exists");
        }

        return $this->insert('attendance_periods', [
            'period_start' => $start,
            'period_end' => $end,
            'description' => $description,
            'status' => 'active'
        ]);
    }

    public function deletePeriod(int $id): bool {
        // Check if period exists
        $period = $this->fetchOne("SELECT * FROM attendance_periods WHERE id = ?", [$id]);
        if (!$period) {
            throw new Exception("Period not found");
        }

        // Check if period has attendance data
        $count = $this->fetchOne(
            "SELECT COUNT(*) as count FROM attendance WHERE period_start = ? AND period_end = ?",
            [$period['period_start'], $period['period_end']]
        );

        if ($count['count'] > 0) {
            throw new Exception("Cannot delete period with existing attendance records ({$count['count']} records)");
        }

        return $this->delete('attendance_periods', 'id = ?', [$id]);
    }

    public function getAttendanceByPeriod(string $periodStart, string $periodEnd): array {
        $sql = "SELECT a.*, e.full_name, e.assignment, e.hourly_rate, e.admin_pay_rate
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                WHERE a.period_start = ? AND a.period_end = ?
                ORDER BY e.assignment, e.full_name, a.date";
        return $this->fetchAll($sql, [$periodStart, $periodEnd]);
    }

    public function getEmployeeAttendanceByPeriod(int $employeeId, string $periodStart, string $periodEnd): array {
        $sql = "SELECT * FROM attendance 
                WHERE employee_id = ? AND period_start = ? AND period_end = ?
                ORDER BY date";
        return $this->fetchAll($sql, [$employeeId, $periodStart, $periodEnd]);
    }

    public function getAttendanceSummaryByPeriod(string $periodStart, string $periodEnd): array {
        $sql = "SELECT 
                    e.id, e.full_name, e.assignment,
                    COUNT(a.id) as days_recorded,
                    SUM(a.hours_worked) as total_hours,
                    SUM(a.overtime) as total_overtime,
                    SUM(a.lates) as total_lates,
                    SUM(a.absences) as total_absences
                FROM employees e
                LEFT JOIN attendance a ON e.id = a.employee_id 
                    AND a.period_start = ? AND a.period_end = ?
                GROUP BY e.id, e.full_name, e.assignment
                ORDER BY e.assignment, e.full_name";
        return $this->fetchAll($sql, [$periodStart, $periodEnd]);
    }
}

