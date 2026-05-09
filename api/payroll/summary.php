<?php
if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$periodStart = $_GET['period_start'] ?? null;
$periodEnd = $_GET['period_end'] ?? null;

if (!$periodStart || !$periodEnd) {
    echo json_encode(['error' => 'Period start and end required']);
    exit;
}

$databaseUrl = getenv('DATABASE_URL');
$db = parse_url($databaseUrl);
$host = $db['host'];
$port = $db['port'] ?? '5432';
$user = $db['user'];
$pass = $db['pass'];
$dbname = ltrim($db['path'], '/');

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get SHS faculty data
$shsStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(gross_pay), 0) as gross, COALESCE(SUM(sss), 0) as sss, COALESCE(SUM(philhealth), 0) as philhealth, COALESCE(SUM(pagibig), 0) as pagibig, COALESCE(SUM(net_pay), 0) as net FROM attendance_faculty_shs WHERE period_start = :start AND period_end = :end");
$shsStmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
$shs = $shsStmt->fetch(PDO::FETCH_ASSOC);

// Get College faculty data
$collegeStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(gross_pay), 0) as gross, COALESCE(SUM(sss), 0) as sss, COALESCE(SUM(philhealth), 0) as philhealth, COALESCE(SUM(pagibig), 0) as pagibig, COALESCE(SUM(net_pay), 0) as net FROM attendance_faculty_college WHERE period_start = :start AND period_end = :end");
$collegeStmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
$college = $collegeStmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'shs' => $shs,
    'college' => $college,
    'total_faculty' => ($shs['count'] ?? 0) + ($college['count'] ?? 0),
    'total_gross' => ($shs['gross'] ?? 0) + ($college['gross'] ?? 0),
    'total_sss' => ($shs['sss'] ?? 0) + ($college['sss'] ?? 0),
    'total_philhealth' => ($shs['philhealth'] ?? 0) + ($college['philhealth'] ?? 0),
    'total_pagibig' => ($shs['pagibig'] ?? 0) + ($college['pagibig'] ?? 0),
    'total_net' => ($shs['net'] ?? 0) + ($college['net'] ?? 0)
]);
?>