<?php

/**
 * Legacy include — prefer: require_once __DIR__ . '/core/bootstrap.php';
 */
require_once __DIR__ . '/core/bootstrap.php';

function bootstrapStartSession(): void
{
    payroll_api_handle_options();
    payroll_session_bootstrap();
}

function bootstrapRequireAuth(): void
{
    require_auth();
}
