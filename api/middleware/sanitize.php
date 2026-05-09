<?php
// ============================================
// INPUT SANITIZATION MIDDLEWARE
// ============================================
// Prevents XSS, SQL Injection, and other injection attacks
// ============================================

class Sanitizer {
    
    // Main sanitization function
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        if (is_string($data)) {
            // Trim whitespace
            $data = trim($data);
            
            // Remove NULL bytes
            $data = str_replace(chr(0), '', $data);
            
            // Remove HTML tags (for text fields)
            $data = strip_tags($data);
            
            // Convert special characters to HTML entities
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Remove control characters
            $data = preg_replace('/[\x00-\x1F\x7F]/u', '', $data);
        }
        
        return $data;
    }
    
    // Sanitize for database (prepared statements handle SQL injection, so this delegates to sanitize())
    public static function sanitizeForDB($data) {
        return self::sanitize($data);
    }
    
    // Sanitize email
    public static function sanitizeEmail($email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    
    // Sanitize URL
    public static function sanitizeURL($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
    
    // Sanitize integer
    public static function sanitizeInt($int) {
        return filter_var($int, FILTER_VALIDATE_INT) ? (int)$int : 0;
    }
    
    // Sanitize float
    public static function sanitizeFloat($float) {
        return filter_var($float, FILTER_VALIDATE_FLOAT) ? (float)$float : 0.0;
    }
    
    // Sanitize boolean
    public static function sanitizeBool($bool) {
        return filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    // Generate secure random string
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    // Validate and sanitize phone number
    public static function sanitizePhone($phone) {
        $phone = preg_replace('/[^0-9+\-()]/', '', $phone);
        return substr($phone, 0, 20);
    }
    
    // Sanitize file name
    public static function sanitizeFilename($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
        return substr($filename, 0, 255);
    }
}

// Usage function for quick access
function sanitize_input($data) {
    return Sanitizer::sanitize($data);
}
