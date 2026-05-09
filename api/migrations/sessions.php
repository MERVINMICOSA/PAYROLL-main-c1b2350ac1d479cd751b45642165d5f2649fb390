<?php

declare(strict_types=1);

/**
 * Migration: sessions_schema
 */

$migrator->run('sessions_schema', 1, function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NULL,
            payload TEXT NOT NULL DEFAULT '',
            last_activity INT NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at INT NOT NULL DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_sessions_last_activity
        ON sessions(last_activity)
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_sessions_user_id
        ON sessions(user_id)
    ");
});

