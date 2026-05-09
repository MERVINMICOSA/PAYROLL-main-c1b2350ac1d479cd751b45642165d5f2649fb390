<?php
// api/config/database.php
// Database configuration for Render PostgreSQL

class DatabaseConfig {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $databaseUrl = getenv('DATABASE_URL');
        
        if ($databaseUrl) {
            // Parse Render's DATABASE_URL
            $db = parse_url($databaseUrl);
            
            $host = $db['host'];
            $port = $db['port'] ?? '5432';
            $user = $db['user'];
            $pass = $db['pass'];
            $dbname = ltrim($db['path'], '/');
            
            // PostgreSQL connection for Render
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
            $this->conn = new PDO($dsn, $user, $pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } else {
            // Fallback for local development
            $host = 'localhost';
            $dbname = 'payroll_db';
            $user = 'root';
            $pass = '';
            
            // MySQL for local
            $this->conn = new mysqli($host, $user, $pass, $dbname);
            if ($this->conn->connect_error) {
                die(json_encode(["error" => "Local DB connection failed"]));
            }
            $this->conn->set_charset("utf8mb4");
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new DatabaseConfig();
        }
        return self::$instance->conn;
    }
}
?>