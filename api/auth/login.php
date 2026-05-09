<?php
// api/auth/login.php

require_once __DIR__ . '/../config/cors-headers.php';
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/session-start.php';

// Get database connection
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    echo json_encode(['success' => false, 'error' => 'Database configuration error']);
    exit;
}

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
    
    $input = json_decode(file_get_contents("php://input"), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, full_name, email, status FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        exit;
    }
    
    if ($user['status'] !== 'Active') {
        echo json_encode(['success' => false, 'error' => 'Account is inactive']);
        exit;
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        exit;
    }
    
    // Clear any existing session data
    session_unset();
    
    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'email' => $user['email'] ?? ''
    ];
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $_SESSION['user'],
        'session_id' => session_id()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

