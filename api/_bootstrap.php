<?php

// Never print warnings/notices into API JSON responses.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Prevent any output corruption
if (ob_get_level() === 0) {
    ob_start();
}

function bootstrapApplyHeaders(): void {
    if (headers_sent()) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://philtech-payroll.onrender.com',
        'https://payroll-main-1.onrender.com',
    ];
    $isLocal = strpos($origin, 'http://localhost') === 0 || strpos($origin, 'http://127.0.0.1') === 0;
    if ($origin !== '' && ($isLocal || in_array($origin, $allowedOrigins, true))) {
        header("Access-Control-Allow-Origin: $origin");
    } elseif ($origin === '') {
        // Non-browser or same-origin requests without explicit Origin header.
        header("Access-Control-Allow-Origin: https://payroll-main-1.onrender.com");
    }
    header("Content-Type: application/json; charset=utf-8");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

// Convert ALL PHP errors into exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    // Respect @-suppressed errors and current reporting level.
    if (!(error_reporting() & $severity)) {
        return false;
    }
    // Keep runtime warnings/notices from crashing API flow.
    $nonFatal = [
        E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE,
        E_DEPRECATED, E_USER_DEPRECATED, E_STRICT,
    ];
    if (in_array($severity, $nonFatal, true)) {
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
    bootstrapApplyHeaders();
    http_response_code($status);
    echo json_encode([
        "success" => $status >= 200 && $status < 300,
        "data" => $data
    ]);
    exit;
}

// Safe error responder
function jsonError($message, $status = 500, $details = null) {
    bootstrapApplyHeaders();
    http_response_code($status);
    echo json_encode([
        "success" => false,
        "error" => $message,
        "details" => $details
    ]);
    exit;
}

function bootstrapStartSession(): void {
    bootstrapApplyHeaders();

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(["success" => true]);
        exit;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionBootstrap = __DIR__ . '/config/session-start.php';
    if (is_file($sessionBootstrap)) {
        require_once $sessionBootstrap;
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
    }

    if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] !== '') {
        @session_id($_COOKIE['PHPSESSID']);
    }
    @session_cache_limiter('');
    @session_start();
}

function bootstrapRequireAuth(): void {
    // Normalize session shape used across legacy/new endpoints.
    if (!isset($_SESSION['user_id']) && isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        $_SESSION['user_id'] = $_SESSION['user']['id'];
    }

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        $hasCookie = isset($_COOKIE[session_name()]) || isset($_COOKIE['PHPSESSID']);
        jsonError('Unauthorized', 401, [
            'hint' => $hasCookie
                ? 'Session cookie arrived but PHP session has no login data — try logging out and back in.'
                : 'No session cookie sent with this API request — use the same origin as login, or SameSite cookie settings blocked the cookie.',
            'session_id' => session_id(),
            'session_status' => session_status(),
            'cookie_name' => session_name(),
            'has_cookie' => $hasCookie,
            'cookie_len' => isset($_COOKIE['PHPSESSID'])
                ? strlen((string)$_COOKIE['PHPSESSID'])
                : (isset($_COOKIE[session_name()]) ? strlen((string)$_COOKIE[session_name()]) : 0),
            'session_keys' => array_keys($_SESSION ?? []),
        ]);
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
