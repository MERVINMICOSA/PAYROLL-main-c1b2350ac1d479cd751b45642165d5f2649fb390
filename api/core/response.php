<?php

declare(strict_types=1);

function bootstrapApplyHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://philtech-payroll.onrender.com',
        'https://payroll-main-1.onrender.com',
    ];
    $isLocal = strpos($origin, 'http://localhost') === 0 || strpos($origin, 'http://127.0.0.1') === 0;
    if ($origin !== '' && ($isLocal || in_array($origin, $allowedOrigins, true))) {
        header("Access-Control-Allow-Origin: $origin");
    } elseif ($origin === '') {
        header('Access-Control-Allow-Origin: https://payroll-main-1.onrender.com');
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

/**
 * Success wrapper: { "success": true|false, "data": ... }
 */
function jsonResponse($data, $status = 200): void
{
    bootstrapApplyHeaders();
    http_response_code((int) $status);
    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'data' => $data,
    ]);
    exit;
}

function jsonError($message, $status = 500, $details = null): void
{
    bootstrapApplyHeaders();
    http_response_code((int) $status);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
    ]);
    exit;
}

/** Raw JSON body (no extra wrapper); applies API headers. */
function json_response(array $data, int $code = 200): void
{
    bootstrapApplyHeaders();
    http_response_code($code);
    echo json_encode($data);
    exit;
}
