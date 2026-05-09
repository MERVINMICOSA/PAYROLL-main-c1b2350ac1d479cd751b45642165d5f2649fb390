<?php
// Central session initialization with secure cookie params
// Include this BEFORE any output in every API endpoint that needs sessions

// Optional: set SESSION_SAVE_PATH on Render / Docker to a writable persistent dir if needed.
$customSavePath = getenv('SESSION_SAVE_PATH');
if ($customSavePath && is_dir($customSavePath) && is_writable($customSavePath)) {
    session_save_path($customSavePath);
}

$cookieParams = session_get_cookie_params();
$isProduction = (getenv('APP_ENV') === 'production' || getenv('NODE_ENV') === 'production');

session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => $isProduction,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

