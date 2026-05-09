<?php

// Prevent any output corruption
ob_start();

// Always return JSON
header("Content-Type: application/json; charset=utf-8");

// Security headers (optional but good)
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight globally
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["success" => true]);
    exit;
}

// Convert ALL PHP errors into exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Catch fatal errors too
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Fatal error",
            "details" => $error["message"]
        ]);
    }
});

// Safe JSON responder
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $status >= 200 && $status < 300,
        "data" => $data
    ]);
    exit;
}

// Safe error responder
function jsonError($message, $status = 500, $details = null) {
    http_response_code($status);
    echo json_encode([
        "success" => false,
        "error" => $message,
        "details" => $details
    ]);
    exit;
}
