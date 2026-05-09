<?php
// Central session initialization — call payroll_session_init() before any output.

/**
 * Start PHP session with the same cookie policy everywhere (auth + attendance APIs).
 */
function payroll_session_init(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $customSavePath = getenv('SESSION_SAVE_PATH');
    if ($customSavePath && is_dir($customSavePath) && is_writable($customSavePath)) {
        session_save_path($customSavePath);
    }

    // Match the cookie name the browser sends (explicit > relying on ini defaults).
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

    if (!session_start()) {
        error_log('payroll_session_init: session_start() failed');
    }
}

payroll_session_init();
