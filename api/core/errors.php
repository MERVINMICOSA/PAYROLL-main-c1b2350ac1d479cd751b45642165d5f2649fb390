<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (ob_get_level() === 0) {
    ob_start();
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $nonFatal = [
        E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE,
        E_DEPRECATED, E_USER_DEPRECATED, E_STRICT,
    ];
    if (in_array($severity, $nonFatal, true)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error',
        'details' => $error['message'],
    ]);
});

set_exception_handler(function (Throwable $e) {
    error_log('API ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        bootstrapApplyHeaders();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
    ]);
    exit;
});
