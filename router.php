<?php
// router.php - Router for Render.com PHP
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Disable caching for HTML files to prevent stale frontend code
function sendNoCacheHeaders() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// If already .php, serve directly
if (strpos($uri, '.php') !== false) {
    $file = __DIR__ . $uri;
    if (file_exists($file)) {
        sendNoCacheHeaders();
        require $file;
        return;
    }
}

// API routes - convert /api/path to /api/path.php
if (strpos($uri, '/api/') === 0) {
    $phpFile = __DIR__ . $uri . '.php';
    
    if (file_exists($phpFile)) {
        require $phpFile;
        return;
    }
    
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found: ' . $uri]);
    return;
}

// Static files (HTML, CSS, JS)
$file = __DIR__ . $uri;
if (is_file($file)) {
    // Send no-cache headers for HTML files
    if (preg_match('/\.(html|htm)$/i', $file)) {
        sendNoCacheHeaders();
    }
    return false;
}

// SPA fallback - always send no-cache for index
sendNoCacheHeaders();
require __DIR__ . '/index.html';
