<?php

declare(strict_types=1);

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    private const DEFAULT_LAST_ACTIVITY = 0;


    private PDO $pdo;
    private int $ttl;

    public function __construct(PDO $pdo, int $ttl = 86400)
    {
        $this->pdo = $pdo;
        $this->ttl = $ttl;
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT payload
             FROM sessions
             WHERE id = :id
               AND last_activity > :time"
        );

        try {
            $stmt->execute([
                ':id' => (string) $id,
                ':time' => time() - $this->ttl,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string) $row['payload'] : '';
        } catch (Throwable $e) {
            error_log('SESSION READ FAILED: ' . $e->getMessage());
            return '';
        }

    }

    public function write($id, $data): bool
    {
        $now = time();

        // best-effort: user_id from current PHP session (if set)
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO sessions (id, user_id, payload, last_activity, ip_address, user_agent, created_at)
             VALUES (:id, :user_id, :payload, :time, :ip, :ua, :created_at)
             ON CONFLICT (id) DO UPDATE SET
                user_id = EXCLUDED.user_id,
                payload = EXCLUDED.payload,
                last_activity = EXCLUDED.last_activity,
                ip_address = EXCLUDED.ip_address,
                user_agent = EXCLUDED.user_agent"
        );

        try {
            $ok = $stmt->execute([
                ':id' => (string) $id,
                ':user_id' => $userId,
                ':payload' => (string) $data,
                ':time' => $now,
                ':created_at' => $now,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            return (bool) $ok;
        } catch (Throwable $e) {
            error_log('SESSION WRITE FAILED: ' . $e->getMessage());
            // degrade gracefully so login/auth endpoints don't crash
            return false;
        }


    }

    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = :id");
        return $stmt->execute([':id' => (string) $id]);
    }

    public function gc($max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM sessions
             WHERE last_activity < :time"
        );

        try {
            $stmt->execute([':time' => time() - (int) $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('SESSION GC FAILED: ' . $e->getMessage());
            return 0;
        }

    }
}

