<?php
// api/reset-password.php
// Run this ONCE to reset accountant password, then DELETE this file

header("Content-Type: text/html");

$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    die("DATABASE_URL not set");
}

try {
    $db = parse_url($databaseUrl);
    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'];
    $dbname = ltrim($db['path'], '/');
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generate new password hash
    $newHash = password_hash('accountant123', PASSWORD_BCRYPT);
    
    // Update accountant password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'accountant'");
    $stmt->execute([$newHash]);
    
    // Also update/create superadmin and oic
    $superadminHash = password_hash('superadmin123', PASSWORD_BCRYPT);
    $oicHash = password_hash('oic123', PASSWORD_BCRYPT);
    
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'superadmin'")->execute([$superadminHash]);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'oic'")->execute([$oicHash]);
    
    echo "<h1 style='color: green;'>✅ Passwords reset successfully!</h1>";
    echo "<p>Accountant: accountant123</p>";
    echo "<p>Superadmin: superadmin123</p>";
    echo "<p>OIC: oic123</p>";
    echo "<p style='color: red;'>⚠️ DELETE this file now!</p>";
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>❌ Error: " . $e->getMessage() . "</h1>";
}
?>