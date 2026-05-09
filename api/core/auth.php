<?php

declare(strict_types=1);

function payroll_auth_user(): ?array
{
    if (!isset($_SESSION['user_id']) && isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        $_SESSION['user_id'] = (int) $_SESSION['user']['id'];
    }

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        return null;
    }

    $u = $_SESSION['user'] ?? null;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $role = null;
    if (is_array($u) && isset($u['role'])) {
        $role = $u['role'];
    } elseif (isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'user' => $u,
        'role' => $role,
    ];
}

/**
 * @return array{id:int,user:?array,role:mixed}
 */
function require_auth(): array
{
    $user = payroll_auth_user();
    if ($user === null) {
        $hasCookie = isset($_COOKIE[session_name()]) || isset($_COOKIE['PHPSESSID']);
        bootstrapApplyHeaders();
        jsonError('Unauthorized', 401, [
            'hint' => $hasCookie
                ? 'Session cookie arrived but PHP session has no login data — try logging out and back in.'
                : 'No session cookie sent with this API request — use the same origin as login, or SameSite cookie settings blocked the cookie.',
            'session_id' => session_id(),
            'session_status' => session_status(),
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
            'cookie_name' => session_name(),
            'cookie_session_name_present' => isset($_COOKIE[session_name()]),
            'cookie_php_sessid_present' => isset($_COOKIE['PHPSESSID']),
            'has_cookie' => $hasCookie,
            'cookie_len' => isset($_COOKIE['PHPSESSID'])
                ? strlen((string) $_COOKIE['PHPSESSID'])
                : (isset($_COOKIE[session_name()]) ? strlen((string) $_COOKIE[session_name()]) : 0),
            'session_keys' => array_keys($_SESSION ?? []),
        ]);
    }

    return $user;
}

/**
 * @return array{id:int,user:?array,role:mixed}
 */
function payroll_require_role(array $allowedRoles): array
{
    $auth = require_auth();
    $user = $auth['user'] ?? null;
    if (!$user || !is_array($user)) {
        bootstrapApplyHeaders();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden - Insufficient privileges',
            'required_roles' => $allowedRoles,
            'your_role' => 'unknown',
        ]);
        exit;
    }

    $userRole = strtolower((string) ($user['role'] ?? ''));
    $allowedRolesLower = array_map('strtolower', $allowedRoles);

    if (!in_array($userRole, $allowedRolesLower, true)) {
        bootstrapApplyHeaders();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden - Insufficient privileges',
            'required_roles' => $allowedRoles,
            'your_role' => $user['role'] ?? 'unknown',
        ]);
        exit;
    }

    return $auth;
}

/** @see payroll_require_role */
function require_role(array $allowedRoles): array
{
    return payroll_require_role($allowedRoles);
}
