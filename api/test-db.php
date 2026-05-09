<?php
// api/test-db.php - Fixed migration script

header("Content-Type: application/json");

$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    echo json_encode(['error' => 'DATABASE_URL environment variable not set']);
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
    
    $results = [];
    
    // ============================================
    // DROP EXISTING TABLES FIRST (to recreate with correct schema)
    // ============================================
    
    $pdo->exec("DROP TABLE IF EXISTS notifications CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS payroll_history CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS archive_log CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS period_settings CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_eda CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_shs_loading CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_college_loading CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_shs_dtr CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_college_dtr CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_admin_pay CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_guard CASCADE");
    $pdo->exec("DROP TABLE IF EXISTS attendance_sa CASCADE");
    
    $results['dropped'] = 'existing tables dropped';
    
    // ============================================
    // CREATE TABLES
    // ============================================
    
    // 1.1 EDA
    $pdo->exec("
        CREATE TABLE attendance_eda (
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
    $results['attendance_eda'] = 'created';
    
    // 1.2 SHS Loading
    $pdo->exec("
        CREATE TABLE attendance_shs_loading (
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
    $results['attendance_shs_loading'] = 'created';
    
    // 1.3 College Loading
    $pdo->exec("
        CREATE TABLE attendance_college_loading (
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
    $results['attendance_college_loading'] = 'created';
    
    // 1.4 SHS DTR
    $pdo->exec("
        CREATE TABLE attendance_shs_dtr (
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
    $results['attendance_shs_dtr'] = 'created';
    
    // 1.5 College DTR
    $pdo->exec("
        CREATE TABLE attendance_college_dtr (
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
    $results['attendance_college_dtr'] = 'created';
    
    // 1.6 Admin Pay
    $pdo->exec("
        CREATE TABLE attendance_admin_pay (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            admin_hours DECIMAL(8,2) DEFAULT 0,
            department VARCHAR(20) DEFAULT 'shs',
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(employee_id, period_start, period_end)
        )
    ");
    $results['attendance_admin_pay'] = 'created';
    
    // 1.7 Guard
    $pdo->exec("
        CREATE TABLE attendance_guard (
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
    $results['attendance_guard'] = 'created';
    
    // 1.8 SA
    $pdo->exec("
        CREATE TABLE attendance_sa (
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
    $results['attendance_sa'] = 'created';
    
    // 2. Payroll History
    $pdo->exec("
        CREATE TABLE payroll_history (
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
    $results['payroll_history'] = 'created';
    
    // 3. Archive Log
    $pdo->exec("
        CREATE TABLE archive_log (
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
    $results['archive_log'] = 'created';
    
    // 4. Notifications (with is_read column)
    $pdo->exec("
        CREATE TABLE notifications (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            data JSONB,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $results['notifications'] = 'created';
    
    // 5. Period Settings
    $pdo->exec("
        CREATE TABLE period_settings (
            id SERIAL PRIMARY KEY,
            current_period_start DATE,
            current_period_end DATE,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $results['period_settings'] = 'created';
    
    // 6. Add rate columns to users table if they don't exist
    $pdo->exec("
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
    $results['user_rates'] = 'added';
    
    // 7. Indexes
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
    
    $results['indexes'] = 'created';
    
    // Insert default period setting
    $pdo->exec("
        INSERT INTO period_settings (current_period_start, current_period_end, updated_by) 
        VALUES (DATE_TRUNC('month', CURRENT_DATE), DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '14 days', 1)
        ON CONFLICT (id) DO NOTHING
    ");
    $results['default_period'] = 'set';
    
    echo json_encode([
        'success' => true,
        'message' => 'Database migration completed successfully',
        'database' => $dbname,
        'host' => $host,
        'tables' => $results
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error',
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}
?>