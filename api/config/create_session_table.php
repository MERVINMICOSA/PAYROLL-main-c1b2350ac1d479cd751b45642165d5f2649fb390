<?php
// api/config/create_session_table.php
require_once __DIR__ . '/database.php';

$conn = DatabaseConfig::getInstance();

// Create sessions table (DB-backed PHP sessions)
$sql = "
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL,
    payload TEXT NOT NULL,
    last_activity INT NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at INT NOT NULL DEFAULT 0
);

-- ensure columns exist for existing installs
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS payload TEXT NOT NULL DEFAULT '';
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS last_activity INT NOT NULL DEFAULT 0;
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45);
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_agent TEXT;
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS created_at INT NOT NULL DEFAULT 0;
ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_id INT;

CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions(last_activity);
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);


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