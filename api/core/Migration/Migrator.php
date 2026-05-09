<?php

declare(strict_types=1);

final class Migrator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function initMigrationsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS migrations (" .
            " id SERIAL PRIMARY KEY," .
            " name VARCHAR(255) UNIQUE NOT NULL," .
            " version INT NOT NULL DEFAULT 1," .
            " applied_at INT NOT NULL" .
            ")"
        );
    }

    public function run(string $name, int $version, callable $callback): void
    {
        $this->initMigrationsTable();

        if ($this->isUpToDate($name, $version)) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $callback($this->pdo);

            // Record migration application (UPSERT)
            $stmt = $this->pdo->prepare(
                "INSERT INTO migrations (name, version, applied_at) " .
                "VALUES (:name, :version, :time) " .
                "ON CONFLICT (name) DO UPDATE SET " .
                "version = EXCLUDED.version, " .
                "applied_at = EXCLUDED.applied_at"
            );

            $stmt->execute([
                ':name' => $name,
                ':version' => $version,
                ':time' => time(),
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log("MIGRATION FAILED: {$name} v{$version} - " . $e->getMessage());
            throw $e;
        }
    }

    private function isUpToDate(string $name, int $version): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT version FROM migrations WHERE name = :name"
        );
        $stmt->execute([':name' => $name]);

        $current = $stmt->fetchColumn();
        return $current !== false && (int) $current >= $version;
    }
}

