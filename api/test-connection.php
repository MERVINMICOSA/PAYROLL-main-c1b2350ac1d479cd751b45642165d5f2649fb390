<?php
// api/test-connection.php - Test database connection
session_start();

header("Content-Type: application/json");

echo json_encode([
    'session_exists' => isset($_SESSION['user_id']),
    'database_url_set' => !empty(getenv('DATABASE_URL')),
    'php_version' => phpversion()
]);

$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl) {
    $db = parse_url($databaseUrl);
    echo json_encode([
        'host' => $db['host'] ?? 'not set',
        'port' => $db['port'] ?? 'not set',
        'dbname' => ltrim($db['path'] ?? '', '/')
    ]);
    
    try {
        $dsn = "pgsql:host=" . $db['host'] . ";port=" . ($db['port'] ?? '5432') . ";dbname=" . ltrim($db['path'], '/') . ";sslmode=require";
        $pdo = new PDO($dsn, $db['user'], $db['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test query
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'tables' => $tables]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>