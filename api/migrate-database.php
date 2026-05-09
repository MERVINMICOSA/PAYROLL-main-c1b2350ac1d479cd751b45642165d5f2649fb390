<?php
// api/migrate-database.php
// Run this once to set up all tables
// Access: https://your-domain.com/api/migrate-database.php

header("Content-Type: application/json");

$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    die(json_encode(['error' => 'DATABASE_URL environment variable not set']));
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
    
    $results = [];
    
    // ============================================
    // 1. ATTENDANCE TABLES
    // ============================================
    
    // 1.1 EDA - Admin Staff Attendance
    $results['attendance_eda'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_eda (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            lates DECIMAL(10,2) DEFAULT 0,
            absences DECIMAL(10,2) DEFAULT 0,
            overtime DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.2 SHS Loading
    $results['attendance_shs_loading'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shs_loading (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            subject TEXT,
            code VARCHAR(20),
            mon DECIMAL(8,2) DEFAULT 0,
            tue DECIMAL(8,2) DEFAULT 0,
            wed DECIMAL(8,2) DEFAULT 0,
            thu DECIMAL(8,2) DEFAULT 0,
            fri DECIMAL(8,2) DEFAULT 0,
            sat DECIMAL(8,2) DEFAULT 0,
            sun DECIMAL(8,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.3 College Loading
    $results['attendance_college_loading'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_college_loading (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            subject TEXT,
            code VARCHAR(20),
            mon DECIMAL(8,2) DEFAULT 0,
            tue DECIMAL(8,2) DEFAULT 0,
            wed DECIMAL(8,2) DEFAULT 0,
            thu DECIMAL(8,2) DEFAULT 0,
            fri DECIMAL(8,2) DEFAULT 0,
            sat DECIMAL(8,2) DEFAULT 0,
            sun DECIMAL(8,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.4 SHS DTR (Daily Time Record)
    $results['attendance_shs_dtr'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shs_dtr (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            daily_data JSONB DEFAULT '{}',
            total_hours DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.5 College DTR
    $results['attendance_college_dtr'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_college_dtr (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            daily_data JSONB DEFAULT '{}',
            total_hours DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.6 Admin Pay (Extra hours for teachers)
    $results['attendance_admin_pay'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_admin_pay (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            admin_hours DECIMAL(8,2) DEFAULT 0,
            department VARCHAR(20) DEFAULT 'shs', -- 'shs', 'college'
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.7 Guard Attendance
    $results['attendance_guard'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_guard (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            daily_data JSONB DEFAULT '{}',
            rate DECIMAL(10,2) DEFAULT 433.33,
            days_worked INT DEFAULT 0,
            total_pay DECIMAL(12,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // 1.8 SA Attendance
    $results['attendance_sa'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_sa (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            daily_data JSONB DEFAULT '{}',
            rate DECIMAL(10,2) DEFAULT 0,
            days_worked INT DEFAULT 0,
            total_pay DECIMAL(12,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    
    // ============================================
    // 2. PAYROLL HISTORY
    // ============================================
    
    $results['payroll_history'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS payroll_history (
            id SERIAL PRIMARY KEY,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            name VARCHAR(100),
            data JSONB NOT NULL,
            total_net DECIMAL(12,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(period_start, period_end)
        )
    ");
    
    // ============================================
    // 3. ARCHIVE STORAGE (Soft delete)
    // ============================================
    
    $results['archive_log'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_log (
            id SERIAL PRIMARY KEY,
            original_table VARCHAR(50) NOT NULL,
            original_id INT NOT NULL,
            original_data JSONB NOT NULL,
            archived_by INT,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            restored_at TIMESTAMP,
            restored_by INT,
            reason TEXT
        )
    ");
    
    // ============================================
    // 4. NOTIFICATIONS
    // ============================================
    
    $results['notifications'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            data JSONB,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // ============================================
    // 5. PERIOD SETTINGS (Global current period)
    // ============================================
    
    $results['period_settings'] = $pdo->exec("
        CREATE TABLE IF NOT EXISTS period_settings (
            id SERIAL PRIMARY KEY,
            current_period_start DATE,
            current_period_end DATE,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // ============================================
    // 6. EMPLOYEE RATES (For teachers)
    // ============================================
    
    // Check if rate columns exist in employees table (Firebase sync)
    // Note: This assumes you have a PostgreSQL users table
    
    $results['check_employee_rates'] = $pdo->exec("
        DO $$ 
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='rate_shs') THEN
                ALTER TABLE users ADD COLUMN rate_shs DECIMAL(10,2) DEFAULT 80;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='rate_college') THEN
                ALTER TABLE users ADD COLUMN rate_college DECIMAL(10,2) DEFAULT 85;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='admin_rate') THEN
                ALTER TABLE users ADD COLUMN admin_rate DECIMAL(10,2) DEFAULT 70;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='users' AND column_name='daily_rate') THEN
                ALTER TABLE users ADD COLUMN daily_rate DECIMAL(10,2) DEFAULT 433.33;
            END IF;
        END $$;
    ");
    
    // ============================================
    // 7. INDEXES for performance
    // ============================================
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_eda_period ON attendance_eda(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shs_loading_period ON attendance_shs_loading(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_college_loading_period ON attendance_college_loading(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shs_dtr_period ON attendance_shs_dtr(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_college_dtr_period ON attendance_college_dtr(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_pay_period ON attendance_admin_pay(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_guard_period ON attendance_guard(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sa_period ON attendance_sa(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payroll_history_period ON payroll_history(period_start, period_end)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Database migration completed',
        'tables_created' => $results
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error: ' . $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
?>