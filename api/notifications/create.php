<?php
// api/notifications/create.php - Create notification (internal use)

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
$userId = $input['user_id'] ?? null;
$type = $input['type'] ?? 'info';
$message = $input['message'] ?? '';
$data = $input['data'] ?? null;

if (!$userId || !$message) {
    echo json_encode(['error' => 'User ID and message required']);
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
    
    // Use local time for created_at
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, data, created_at) 
        VALUES (:user_id, :type, :message, :data, NOW() AT TIME ZONE 'UTC')
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':type' => $type,
        ':message' => $message,
        ':data' => $data ? json_encode($data) : null
    ]);
    
    echo json_encode(['success' => true, 'notification_id' => $pdo->lastInsertId()]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>