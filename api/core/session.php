<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/session-start.php';
require_once __DIR__ . '/Session/DatabaseSessionHandler.php';
require_once __DIR__ . '/database.php';


function payroll_api_handle_options(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
        return;
    }
    bootstrapApplyHeaders();
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

function payroll_session_bootstrap(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Single entry point: payroll_session_init() + session_start() happen there.
    // Do not start sessions anywhere else.
    payroll_session_init();
}

