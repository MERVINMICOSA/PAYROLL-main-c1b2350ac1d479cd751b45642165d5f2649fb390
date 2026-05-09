<?php
// api/run-payroll-test.php - Interactive payroll testing script
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Database.php';
require_once __DIR__ . '/models/SecureDatabase.php';

$db = new SecureDatabase();

echo "=== PAYROLL TESTING SCRIPT ===\n\n";

echo "1. Available periods:\n";
$periods = $db->fetchAll("SELECT * FROM period_settings ORDER BY updated_at DESC LIMIT 5");
foreach ($periods as $p) {
    echo "   - {$p['current_period_start']} to {$p['current_period_end']}\n";
}
echo "\n";

echo "2. Test employees:\n";
$emps = $db->fetchAll("SELECT id, full_name, assignment, hourly_rate FROM employees LIMIT 5");
foreach ($emps as $emp) {
    echo "   - ID:{$emp['id']} {$emp['full_name']} ({$emp['assignment']}) @ ₱{$emp['hourly_rate']}/hr\n";
}
echo "\n";

echo "3. Attendance summary for recent period:\n";
$recentPeriod = $periods[0] ?? null;
if ($recentPeriod) {
    $att = $db->fetchAll("
        SELECT e.full_name, SUM(a.hours_worked) as total_hours, SUM(a.overtime) as ot_hours
        FROM attendance a JOIN employees e ON a.employee_id = e.id
        WHERE a.period_start = ? AND a.period_end = ?
        GROUP BY a.employee_id, e.full_name
        ORDER BY total_hours DESC LIMIT 5
    ", [$recentPeriod['current_period_start'], $recentPeriod['current_period_end']]);
    foreach ($att as $a) {
        echo "   - {$a['full_name']}: {$a['total_hours']}h + {$a['ot_hours']} OT\n";
    }
} else {
    echo "   No periods found\n";
}
echo "\n";

echo "4. Current payroll records (Pending):\n";
$payroll = $db->fetchAll("SELECT p.id, e.full_name, p.period, p.net_salary, p.status FROM payroll p JOIN employees e ON p.employee_id = e.id WHERE p.status = 'Pending' ORDER BY p.id DESC LIMIT 5");
foreach ($payroll as $pr) {
    echo "   - ID:{$pr['id']} {$pr['full_name']} ({$pr['period']}) = ₱" . number_format($pr['net_salary'], 2) . " {$pr['status']}\n";
}
echo "\n";

echo "✅ Test data ready! Use payroll.html → Generate button to test.\n";
echo "To clear test data: DELETE FROM payroll WHERE period_start = '2025-01-01';\n";
?>

