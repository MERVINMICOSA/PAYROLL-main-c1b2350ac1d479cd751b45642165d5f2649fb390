<?php
header("Content-Type: application/json");

require_once __DIR__ . '/models/SecureDatabase.php';

try {
    $db = new SecureDatabase();
    
    // Update passwords to match hardcoded ones
    $accountantHash = password_hash('accountant123', PASSWORD_BCRYPT);
    $superadminHash = password_hash('superadmin123', PASSWORD_BCRYPT);
    $oicHash = password_hash('oic123', PASSWORD_BCRYPT);
    
    $db->executeQuery("UPDATE users SET password_hash = ? WHERE username = 'accountant'", [$accountantHash]);
    $db->executeQuery("UPDATE users SET password_hash = ? WHERE username = 'superadmin'", [$superadminHash]);
    $db->executeQuery("UPDATE users SET password_hash = ? WHERE username = 'oic'", [$oicHash]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Passwords updated to match hardcoded values',
        'credentials' => [
            'accountant' => 'accountant123',
            'superadmin' => 'superadmin123',
            'oic' => 'oic123'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>