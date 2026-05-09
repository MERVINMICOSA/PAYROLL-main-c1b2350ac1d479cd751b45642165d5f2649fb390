<?php
// api/auth/get-user-role.php - Get user role from PostgreSQL
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/../models/SecureDatabase.php';

$input = json_decode(file_get_contents("php://input"), true);
$email = $input['email'] ?? '';

if (empty($email)) {
    echo json_encode(['role' => 'teacher', 'error' => 'No email provided']);
    exit;
}

try {
    $db = new SecureDatabase();
    
    // Extract username from email (e.g., accountant@philtech.edu -> accountant)
    $username = explode('@', $email)[0];
    
    // Check if user exists in PostgreSQL
    $user = $db->fetchOne("SELECT role FROM users WHERE username = ? OR email = ?", [$username, $email]);
    
    if ($user) {
        echo json_encode(['role' => $user['role']]);
    } else {
        // Default role for new users
        echo json_encode(['role' => 'teacher']);
    }
} catch (Exception $e) {
    echo json_encode(['role' => 'teacher', 'error' => $e->getMessage()]);
}
?>