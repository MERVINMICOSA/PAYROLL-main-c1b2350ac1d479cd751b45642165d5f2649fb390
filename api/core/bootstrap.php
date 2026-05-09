<?php

declare(strict_types=1);

define('APP_BOOTSTRAP_ALLOWED', true);
require_once __DIR__ . '/Kernel.php';

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);

    define('PAYROLL_API_ROOT', dirname(__DIR__));

    require_once __DIR__ . '/response.php';
    require_once __DIR__ . '/errors.php';
    require_once __DIR__ . '/session.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/database.php';

    payroll_api_handle_options();
    payroll_session_bootstrap();
    bootstrapApplyHeaders();
}
