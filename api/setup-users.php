<?php
// api/setup-users.php
// Run this file once by visiting: https://your-app.onrender.com/api/setup-users.php
// Then DELETE or rename this file after running!

header('Content-Type: text/html');
echo "<h1>User Setup Utility</h1>";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Database.php';

try {
    $db = new Database();
    
    // Check if users already exist
    $existingUsers = $db->getAllUsers();
    
    if (count($existingUsers) > 0) {
        echo "<p style='color: orange;'>⚠️ Users already exist in database!</p>";
        echo "<pre>";
        print_r($existingUsers);
        echo "</pre>";
        echo "<p>If you want to reset passwords, run this SQL in your database manager:</p>";
        echo "<code>DELETE FROM sessions; DELETE FROM users;</code>";
        echo "<p>Then refresh this page.</p>";
        exit;
    }
    
    // Generate password hashes
    $accountantHash = password_hash('accountant123', PASSWORD_BCRYPT);
    $superadminHash = password_hash('superadmin123', PASSWORD_BCRYPT);
    $oicHash = password_hash('oic123', PASSWORD_BCRYPT);
    
    // Insert users via raw PDO
    $conn = DatabaseConfig::getInstance();
    $stmt = $conn->prepare("
        INSERT INTO users (username, password_hash, full_name, role, status, created_at, updated_at)
        VALUES
        ('accountant', :hash1, 'School Accountant', 'accountant', 'Active', NOW(), NOW()),
        ('superadmin', :hash2, 'Super Administrator', 'superadmin', 'Active', NOW(), NOW()),
        ('oic', :hash3, 'OIC Head', 'oic', 'Active', NOW(), NOW())
    ");
    $stmt->execute([
        ':hash1' => $accountantHash,
        ':hash2' => $superadminHash,
        ':hash3' => $oicHash
    ]);
    
    echo "<p style='color: green;'>✅ Users created successfully!</p>";
    echo "<h3>Login Credentials:</h3>";
    echo "<ul>";
    echo "<li><strong>Accountant:</strong> username: 'accountant', password: 'accountant123'</li>";
    echo "<li><strong>Superadmin:</strong> username: 'superadmin', password: 'superadmin123'</li>";
    echo "<li><strong>OIC:</strong> username: 'oic', password: 'oic123'</li>";
    echo "</ul>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ IMPORTANT: Delete this file (api/setup-users.php) after successful setup!</p>";
    echo "<p><a href='/index.html'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure your database tables are created first. Run api/config/schema.sql on your database.</p>";
}
?>