<?php
/**
 * Temporary session diagnostics — enable with SESSION_DIAGNOSTICS=1 in the environment.
 * Disable (unset or 0) and remove this file once issues are confirmed fixed.
 *
 * Call from the browser with the same origin as the app, with credentials:
 *   fetch('/api/auth/session_diagnostics.php', { credentials: 'include' })
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Stealth off by default — avoids exposing session internals when env is not set.
if (getenv('SESSION_DIAGNOSTICS') !== '1') {
    http_response_code(404);
    exit;
}

$status = session_status();
$statusLabels = [
    PHP_SESSION_DISABLED => 'PHP_SESSION_DISABLED',
    PHP_SESSION_NONE => 'PHP_SESSION_NONE',
    PHP_SESSION_ACTIVE => 'PHP_SESSION_ACTIVE',
];

$savePath = session_save_path();
$sid = session_id();
$sessionFile = ($sid !== '' && $savePath !== '')
    ? rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sess_' . $sid
    : '';

jsonResponse([
    'session_id' => $sid,
    'session_status' => $status,
    'session_status_label' => $statusLabels[$status] ?? 'unknown',
    'session_data' => $_SESSION,
    'session_keys' => array_keys($_SESSION ?? []),
    'save_path' => $savePath,
    'save_path_writable' => $savePath !== '' && is_dir($savePath) && is_writable($savePath),
    'session_file' => $sessionFile,
    'session_file_exists' => $sessionFile !== '' && is_file($sessionFile),
    'ini_session_save_path' => ini_get('session.save_path'),
    'cookie_name' => session_name(),
    'cookie_php_sessid_present' => isset($_COOKIE['PHPSESSID']),
    'cookie_len' => isset($_COOKIE['PHPSESSID']) ? strlen((string) $_COOKIE['PHPSESSID']) : 0,
    'session_save_path_env_set' => getenv('SESSION_SAVE_PATH') !== false && getenv('SESSION_SAVE_PATH') !== '',
], 200);
