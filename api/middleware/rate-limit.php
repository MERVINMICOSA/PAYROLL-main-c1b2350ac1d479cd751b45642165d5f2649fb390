<?php
// ============================================
// RATE LIMITING MIDDLEWARE
// ============================================
// Prevents brute force attacks and DoS
// ============================================

class RateLimiter {
    private $limit;
    private $window;
    private $storagePath;
    
    public function __construct($limit = 50, $window = 60) {
        $this->limit = $limit;      // requests per window
        $this->window = $window;    // window in seconds
        $this->storagePath = sys_get_temp_dir() . '/philtech_rate_limits/';
        
        // Create storage directory if not exists
        if (!file_exists($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }
    
    public function check($identifier, $endpoint = 'global') {
        $key = $this->getKey($identifier, $endpoint);
        $data = $this->getData($key);
        
        $now = time();
        
        // Clean old entries
        $data = array_filter($data, function($timestamp) use ($now) {
            return $timestamp > $now - $this->window;
        });
        
        // Check if limit exceeded
        if (count($data) >= $this->limit) {
            $retryAfter = $this->window - ($now - min($data));
            $this->sendRateLimitResponse($retryAfter);
            exit;
        }
        
        // Add current request
        $data[] = $now;
        $this->saveData($key, $data);
        
        return true;
    }
    
    public function checkLogin($ip, $username = null) {
        // Stricter limits for login attempts
        $this->limit = 5;
        $this->window = 300; // 5 minutes
        
        $key = $this->getKey($ip, 'login');
        $data = $this->getData($key);
        
        $now = time();
        $data = array_filter($data, function($timestamp) use ($now) {
            return $timestamp > $now - $this->window;
        });
        
        if (count($data) >= $this->limit) {
            $retryAfter = $this->window - ($now - min($data));
            $this->sendRateLimitResponse($retryAfter, 'Too many login attempts');
            exit;
        }
        
        $data[] = $now;
        $this->saveData($key, $data);
        
        return true;
    }
    
    public function checkAPI($ip, $apiKey = null) {
        $this->limit = 100;
        $this->window = 60;
        
        return $this->check($ip, 'api');
    }
    
    private function getKey($identifier, $endpoint) {
        return md5($identifier . '_' . $endpoint);
    }
    
    private function getData($key) {
        $file = $this->storagePath . $key . '.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true) ?: [];
        }
        return [];
    }
    
    private function saveData($key, $data) {
        $file = $this->storagePath . $key . '.json';
        file_put_contents($file, json_encode($data));
    }
    
    private function sendRateLimitResponse($retryAfter, $message = 'Too many requests') {
        http_response_code(429);
        header("Retry-After: $retryAfter");
        header('Content-Type: application/json');
        
        echo json_encode([
            'error' => $message,
            'retry_after' => $retryAfter,
            'message' => "Please wait {$retryAfter} seconds before trying again."
        ]);
    }
    
    // Clean up old files (call periodically)
    public function cleanup() {
        $files = glob($this->storagePath . '*.json');
        $now = time();
        
        foreach ($files as $file) {
            if (filemtime($file) < $now - 86400) { // older than 24 hours
                unlink($file);
            }
        }
    }
}