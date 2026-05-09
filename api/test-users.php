<?php
header("Content-Type: application/json");

require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    // Check if users table exists
    $stmt = $db->executeQuery("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'users')");
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        echo json_encode(['error' => 'Users table does not exist']);
        exit;
    }
    
    // Get users
    $users = $db->fetchAll("SELECT id, username, role FROM users");
    
    echo json_encode([
        'success' => true,
        'user_count' => count($users),
        'users' => $users
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>