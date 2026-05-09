<?php
// api/notifications/mark-read.php - Mark notification as read

if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://philtech-payroll.onrender.com");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$notificationId = $input['notification_id'] ?? null;
$markAll = $input['mark_all'] ?? false;

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
    
    if ($markAll) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE");
        $stmt->execute([':user_id' => $userId]);
    } elseif ($notificationId) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>