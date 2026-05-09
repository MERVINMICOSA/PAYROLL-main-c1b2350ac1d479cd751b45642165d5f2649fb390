<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/session-start.php';

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

    payroll_session_init();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $incomingId = $_COOKIE['PHPSESSID'] ?? '';
        if ($incomingId !== '') {
            session_name('PHPSESSID');
            session_id($incomingId);
            session_cache_limiter('');
            @ini_set('session.use_cookies', '0');
            @ini_set('session.use_only_cookies', '0');
            @session_start();
        }
    }
}
