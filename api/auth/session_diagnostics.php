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

    $pdo = bootstrapGetPdo();
    $sid = session_id();

    $stmt = $pdo->prepare("SELECT id, user_id, last_activity, created_at FROM sessions WHERE id = :id");
    $stmt->execute([':id' => $sid]);
    $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);

jsonResponse([
    'session_id' => $sid,
    'session_status' => $status,
    'session_status_label' => $statusLabels[$status] ?? 'unknown',
    'session_data' => $_SESSION,
    'session_keys' => array_keys($_SESSION ?? []),
    'db_session_found' => (bool) $dbRow,
    'db_last_activity' => $dbRow['last_activity'] ?? null,
    'db_user_id' => $dbRow['user_id'] ?? null,
    'ini_session_save_path' => ini_get('session.save_path'),

    'cookie_name' => session_name(),
    'cookie_php_sessid_present' => isset($_COOKIE['PHPSESSID']),
    'cookie_len' => isset($_COOKIE['PHPSESSID']) ? strlen((string) $_COOKIE['PHPSESSID']) : 0,
    'session_save_path_env_set' => getenv('SESSION_SAVE_PATH') !== false && getenv('SESSION_SAVE_PATH') !== '',
], 200);
