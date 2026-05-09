<?php
// api/config/firebase-config.php - Firebase Auth Config
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Firebase config from environment variables
$apiKey = getenv('FIREBASE_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Firebase API key not configured on server'
    ]);
    exit;
}

$firebaseConfig = [
    'apiKey' => $apiKey,
    'authDomain' => getenv('FIREBASE_AUTH_DOMAIN') ?: 'philtech-payroll.firebaseapp.com',
    'projectId' => getenv('FIREBASE_PROJECT_ID') ?: 'philtech-payroll',
    'storageBucket' => getenv('FIREBASE_STORAGE_BUCKET') ?: 'philtech-payroll.firebasestorage.app',
    'messagingSenderId' => getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '988193021445',
    'appId' => getenv('FIREBASE_APP_ID') ?: '1:988193021445:web:20553630a83c8db5e8066c'
];

echo json_encode([
    'success' => true,
    'config' => $firebaseConfig
]);
?>
