<?php

// Never print warnings/notices into API JSON responses.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Prevent any output corruption
if (ob_get_level() === 0) {
    ob_start();
}

// Always return JSON
if (!headers_sent()) {
    header("Content-Type: application/json; charset=utf-8");
}

// Security headers (optional but good)
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

// Handle preflight globally
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["success" => true]);
    exit;
}

// Convert ALL PHP errors into exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    // Respect @-suppressed errors and current reporting level.
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Catch fatal errors too
register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    // Only treat truly fatal engine errors as fatal JSON responses.
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
    }

    echo json_encode([
        "success" => false,
        "error" => "Fatal error",
        "details" => $error["message"]
    ]);
});

// Safe JSON responder
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $status >= 200 && $status < 300,
        "data" => $data
    ]);
    exit;
}

// Safe error responder
function jsonError($message, $status = 500, $details = null) {
    http_response_code($status);
    echo json_encode([
        "success" => false,
        "error" => $message,
        "details" => $details
    ]);
    exit;
}

function bootstrapStartSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] !== '') {
        // Suppress warning text; keep cookie-bound session continuity.
        @session_id($_COOKIE['PHPSESSID']);
    }

    // Avoid cache-limiter header warnings when output already started.
    @session_cache_limiter('');
    // Suppress runtime warning text; errors are still logged via log_errors.
    @session_start();
}

function bootstrapRequireAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        jsonError('Unauthorized', 401);
    }
}

function bootstrapJsonInput(): array {
    $raw = file_get_contents("php://input");
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function bootstrapGetPdo(string $sslmode = 'require'): PDO {
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
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (Throwable $e) {
        jsonError('Database connection failed', 500, $e->getMessage());
    }
}
