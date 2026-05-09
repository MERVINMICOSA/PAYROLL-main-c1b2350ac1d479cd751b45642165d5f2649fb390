<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/Migration/Migrator.php';

$pdo = bootstrapGetPdo();
$migrator = new Migrator($pdo);

// Run migrations (non-destructive, idempotent)
require_once __DIR__ . '/migrations/sessions.php';

// Safe auto-heal (add any missing columns without DROP)
$migrator->run('sessions_add_columns_safe', 1, function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_id INT');
    $pdo->exec('ALTER TABLE sessions ADD COLUMN IF NOT EXISTS payload TEXT');
    $pdo->exec('ALTER TABLE sessions ADD COLUMN IF NOT EXISTS last_activity INT');
    $pdo->exec('ALTER TABLE sessions ADD COLUMN IF NOT EXISTS created_at INT');
});

echo json_encode([
    'success' => true,
    'migrations_completed' => true,
], JSON_PRETTY_PRINT);

