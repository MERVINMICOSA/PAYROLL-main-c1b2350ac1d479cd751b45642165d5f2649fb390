<?php

declare(strict_types=1);

if (defined('APP_KERNEL_LOADED')) {
    return;
}

define('APP_KERNEL_LOADED', true);

if (!defined('APP_BOOTSTRAP_ALLOWED')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Kernel must be loaded via core/bootstrap.php only',
    ]);
    exit;
}

