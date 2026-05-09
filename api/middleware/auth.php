<?php
// api/middleware/auth.php — Session-based authentication helpers

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/../core/bootstrap.php';
}

function validateSession()
{
    $auth = require_auth();

    return $auth['user'];
}

function getCurrentUser()
{
    $a = payroll_auth_user();

    return $a ? $a['user'] : null;
}

/**
 * @param array $allowedRoles List of allowed roles (e.g., ['superadmin', 'accountant'])
 * @return array User data if authorized
 */
function requireRole(array $allowedRoles)
{
    $auth = payroll_require_role($allowedRoles);

    return $auth['user'];
}
