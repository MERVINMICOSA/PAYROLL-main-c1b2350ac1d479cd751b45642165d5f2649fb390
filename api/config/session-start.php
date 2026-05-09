<?php
// Central session initialization — call payroll_session_init() before any output.

/**
 * Resolve PHP session save path: mounted disk (env) or app-managed storage only.
 * ENV path is never auto-created — must exist and be writable or we fall back.
 * App storage may be mkdir'd. No sys_get_temp_dir() / /tmp fallback.
 *
 * @throws RuntimeException if no writable path is available
 */
function payroll_resolve_session_save_path(): string
{

    $envRaw = getenv('SESSION_SAVE_PATH');
    if ($envRaw !== false && trim((string) $envRaw) !== '') {
        $env = trim((string) $envRaw);
        if (!is_dir($env)) {
            error_log('SESSION ERROR: ENV path does not exist: ' . $env);
        } elseif (!is_writable($env)) {
            error_log('SESSION ERROR: ENV path not writable: ' . $env);
        } else {
            return $env;
        }
    }

    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'php-sessions';

    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            error_log('SESSION ERROR: could not create fallback path: ' . $path);
            error_log('SESSION ERROR: No writable session save path found');
            error_log('ENV SESSION_SAVE_PATH=' . ($envRaw === false ? '' : (string) $envRaw));
            throw new RuntimeException('No writable session storage available');
        }
    }

    if (!is_writable($path)) {
        error_log('SESSION ERROR: fallback path not writable: ' . $path);
        error_log('SESSION ERROR: No writable session save path found');
        error_log('ENV SESSION_SAVE_PATH=' . ($envRaw === false ? '' : (string) $envRaw));
        throw new RuntimeException('No writable session storage available');
    }

    return $path;
}

/**
 * Start PHP session with the same cookie policy everywhere (auth + attendance APIs).
 */
function payroll_session_init(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Hard contract: handler must be registered before session_start() every time.
    // Do not throw here in production; instead fail gracefully to avoid 500 loops.
    if (headers_sent()) {
        error_log('payroll_session_init: headers already sent before session init');
        return;
    }



    // DB-backed sessions (no filesystem dependency)
    $pdo = bootstrapGetPdo();
    $handler = new DatabaseSessionHandler($pdo);

    session_set_save_handler($handler, true);

    // Match the cookie name the browser sends.
    session_name('PHPSESSID');

    $cookieParams = session_get_cookie_params();
    $isProduction = (getenv('APP_ENV') === 'production' || getenv('NODE_ENV') === 'production');

    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'] ?? 0,
        'path'     => '/',
        'domain'   => $cookieParams['domain'] ?? '',
        'secure'   => $isProduction,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        if (!session_start()) {
            error_log('payroll_session_init: session_start() failed');
        }
    }
}


