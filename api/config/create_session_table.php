<?php
// api/config/create_session_table.php
require_once __DIR__ . '/database.php';

$conn = DatabaseConfig::getInstance();

// Create sessions table
$sql = "
CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT
)";

if ($conn instanceof PDO) {
    $conn->exec($sql);
    echo "✅ Sessions table created successfully!";
} else {
    // MySQL version
    $conn->query("
        CREATE TABLE IF NOT EXISTS sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT
        )
    ");
    echo "✅ Sessions table created successfully!";
}
?>