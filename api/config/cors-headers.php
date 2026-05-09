<?php
// api/config/cors-headers.php
// Centralized CORS configuration for all API endpoints

// Get the requesting origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Allow any localhost origin for development, and the production domain
$isLocalhost = (strpos($origin, 'http://localhost') === 0 || strpos($origin, 'http://127.0.0.1') === 0);
$isProduction = (strpos($origin, 'https://philtech-payroll.onrender.com') === 0);

if ($isLocalhost || $isProduction || empty($origin)) {
    header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Access-Control-Max-Age: 86400");

// Prevent caching of API responses
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Set JSON content type if not already set
if (!headers_sent()) {
    header("Content-Type: application/json; charset=utf-8");
}

