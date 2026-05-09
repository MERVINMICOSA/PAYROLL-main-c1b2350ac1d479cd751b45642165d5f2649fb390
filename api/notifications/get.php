<?php
// api/notifications/get.php - Get user notifications

if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$databaseUrl = getenv('DATABASE_URL');

try {
    $db = parse_url($databaseUrl);
    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'];
    $dbname = ltrim($db['path'], '/');
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $userId = $_SESSION['user_id'];
    
    // Get unread count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = FALSE");
    $countStmt->execute([':user_id' => $userId]);
    $unreadCount = $countStmt->fetchColumn();
    
    // Get recent notifications - convert to Philippines timezone (UTC+8)
    $stmt = $pdo->prepare("
        SELECT id, type, message, data, is_read, 
               (created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Manila') as created_at_local
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([':user_id' => $userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rename created_at_local to created_at for frontend
    foreach ($notifications as &$notif) {
        $notif['created_at'] = $notif['created_at_local'];
        unset($notif['created_at_local']);
    }
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => $notifications
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>