<?php
// Central session initialization with secure cookie params
// Include this BEFORE any output in every API endpoint that needs sessions

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

