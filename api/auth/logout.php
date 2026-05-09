<?php
// api/auth/logout.php - Enhanced for cross-tab logout

require_once __DIR__ . '/../core/bootstrap.php';

// Get session info before destroying (for logging if needed)
$sessionUser = isset($_SESSION['user']) ? $_SESSION['user']['username'] ?? 'unknown' : 'none';
$sessionId = session_id();

// Clear session data first
$_SESSION = [];

// Get cookie params for proper cookie clearing
$cookieParams = session_get_cookie_params();

// Destroy session
session_unset();
session_destroy();

// Ensure session cookie is cleared properly
// Set exact same parameters as when it was created
$isProduction = (getenv('APP_ENV') === 'production' || getenv('NODE_ENV') === 'production');

setcookie(
    session_name(),
    '',
    time() - 42000,
    $cookieParams['path'] ?? '/',
    $cookieParams['domain'] ?? '',
    $isProduction,
    $cookieParams['httponly'] ?? true
);

// Return success with session indicator (for cross-tab sync)
echo json_encode([
    'success' => true, 
    'message' => 'Logged out',
    'session_destroyed' => true,
    'previous_session' => $sessionId
]);
?>
