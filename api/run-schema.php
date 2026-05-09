<?php
header("Content-Type: application/json");

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/SecureDatabase.php';

$response = ['steps' => []];

try {
    // Ensure we can execute arbitrary SQL from schema.sql.
    // SecureDatabase::executeQuery() is not guaranteed to be public.
    // We'll use the raw PDO connection from DatabaseConfig.
    $pdo = DatabaseConfig::getInstance();
    
    // Read the schema.sql file
    $schemaFile = __DIR__ . '/config/schema.sql';
    
    if (!file_exists($schemaFile)) {
        $response['error'] = 'schema.sql file not found at: ' . $schemaFile;
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    $sql = file_get_contents($schemaFile);

    // Split SQL statements (basic split by semicolon).
    // Note: schema.sql is authored as simple DDL/DML and works with this approach.
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (empty($statement)) continue;

        try {
            $pdo->exec($statement);
            $response['steps'][] = 'Executed: ' . substr($statement, 0, 50) . '...';
        } catch (Exception $e) {
            // "already exists" is expected for idempotent reruns.
            if (strpos(strtolower($e->getMessage()), 'already exists') === false) {
                $response['warnings'][] = $e->getMessage();
            }
        }
    }

    // Seed default users if none exist
    $result = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC);
    $userCount = (int)($result['count'] ?? 0);
    $response['user_count'] = $userCount;

    if ($userCount === 0) {
        $accountantHash = password_hash('admin123', PASSWORD_BCRYPT);
        $superadminHash = password_hash('superadmin123', PASSWORD_BCRYPT);

        $stmt1 = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, role, status)
            VALUES ('accountant', ?, 'School Accountant', 'accountant', 'Active')
        ");
        $stmt1->execute([$accountantHash]);

        $stmt2 = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, role, status)
            VALUES ('superadmin', ?, 'Super Administrator', 'superadmin', 'Active')
        ");
        $stmt2->execute([$superadminHash]);

        $response['steps'][] = 'Default users created';

        $result = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch(PDO::FETCH_ASSOC);
        $response['user_count'] = (int)($result['count'] ?? 0);
    }

    // List all tables
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")
                  ->fetchAll(PDO::FETCH_ASSOC);
    $response['tables'] = array_column($tables, 'table_name');
    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
