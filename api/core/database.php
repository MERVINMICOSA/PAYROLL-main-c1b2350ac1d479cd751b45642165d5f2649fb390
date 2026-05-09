<?php

declare(strict_types=1);

function bootstrapJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function bootstrapGetPdo(string $sslmode = 'require'): PDO
{
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        jsonError('Missing DATABASE_URL', 500);
    }

    $db = parse_url($databaseUrl);
    if (!$db || !isset($db['host'], $db['user'], $db['path'])) {
        jsonError('Invalid database configuration', 500);
    }

    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'] ?? '';
    $dbname = ltrim($db['path'], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    if ($sslmode !== '') {
        $dsn .= ";sslmode=$sslmode";
    }

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        jsonError('Database connection failed', 500, $e->getMessage());
    }

    throw new RuntimeException('bootstrapGetPdo: unreachable');
}
