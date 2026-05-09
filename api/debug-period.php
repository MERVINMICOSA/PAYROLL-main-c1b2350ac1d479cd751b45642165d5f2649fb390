<?php
// api/debug-period.php - Debug period settings

session_start();

$allowed_origins = array(
    'http://localhost:5500',
    'http://localhost:3000',
    'http://localhost:8000',
    'http://127.0.0.1:5500',
    'https://philtech-payroll.onrender.com'
);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/config/database.php';

try {
    $pdo = DatabaseConfig::getInstance();
    
    // Check if table exists
    $tableCheck = $pdo->query("SELECT to_regclass('public.period_settings')");
    $tableExists = $tableCheck->fetch()[0];
    
    $response = [
        'table_exists' => !is_null($tableExists),
        'table_name' => $tableExists ?? 'period_settings NOT FOUND'
    ];
    
    if (!is_null($tableExists)) {
        // Get all records from period_settings
        $stmt = $pdo->query("SELECT * FROM period_settings ORDER BY id DESC");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['records_count'] = count($records);
        $response['records'] = $records;
        
        // Check structure
        $structStmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'period_settings' 
            ORDER BY ordinal_position
        ");
        $response['columns'] = $structStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>
