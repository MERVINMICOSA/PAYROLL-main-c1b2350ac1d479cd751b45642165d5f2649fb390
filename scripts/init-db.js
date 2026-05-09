#!/usr/bin/env node
/**
 * Database Initialization Script
 * Creates all required tables and seeds initial data
 * 
 * Usage:
 *   npm run db:init
 *   or
 *   node scripts/init-db.js
 */

require('dotenv').config();
const { Pool } = require('pg');
const bcrypt = require('bcryptjs');

// ========== CONFIGURATION ==========
const getConnectionString = () => {
    if (process.env.DATABASE_URL) {
        return process.env.DATABASE_URL;
    }
    
    if (process.env.PGHOST && process.env.PGUSER && process.env.PGPASSWORD && process.env.PGDATABASE) {
        const user = encodeURIComponent(process.env.PGUSER);
        const password = encodeURIComponent(String(process.env.PGPASSWORD));
        const host = process.env.PGHOST;
        const port = process.env.PGPORT || '5432';
        const database = process.env.PGDATABASE;
        return `postgresql://${user}:${password}@${host}:${port}/${database}`;
    }

    throw new Error('❌ DATABASE_URL or PGHOST/PGUSER/PGPASSWORD/PGDATABASE must be set');
};

const pool = new Pool({
    connectionString: getConnectionString(),
    ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});

// ========== ERROR HANDLING ==========
pool.on('error', (err) => {
    console.error('❌ Unexpected error on idle client:', err);
});

// ========== MIGRATION FUNCTIONS ==========
async function createTables() {
    console.log('\n📋 Creating tables...');
    
    const tables = [
        {
            name: 'users',
            sql: `
                CREATE TABLE IF NOT EXISTS users (
                    id SERIAL PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    full_name VARCHAR(120) NOT NULL,
                    email VARCHAR(120),
                    phone VARCHAR(20),
                    role VARCHAR(20) DEFAULT 'teacher',
                    status VARCHAR(20) DEFAULT 'Active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        },
        {
            name: 'sessions',
            sql: `
                CREATE TABLE IF NOT EXISTS sessions (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    token VARCHAR(255) UNIQUE NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45),
                    user_agent TEXT
                );
            `
        },
        {
            name: 'employees',
            sql: `
                CREATE TABLE IF NOT EXISTS employees (
                    id SERIAL PRIMARY KEY,
                    employee_id VARCHAR(20) UNIQUE,
                    full_name VARCHAR(120) NOT NULL,
                    position VARCHAR(80),
                    department VARCHAR(80),
                    employment_type VARCHAR(20) DEFAULT 'Regular',
                    base_salary DECIMAL(10,2) DEFAULT 0,
                    hourly_rate DECIMAL(10,2) DEFAULT 0,
                    admin_pay_rate DECIMAL(10,2) DEFAULT 0,
                    email VARCHAR(120),
                    phone VARCHAR(20),
                    birth_date DATE,
                    hire_date DATE,
                    assignment VARCHAR(20) DEFAULT 'regular',
                    rate_shs DECIMAL(10,2) DEFAULT 80,
                    rate_college DECIMAL(10,2) DEFAULT 85,
                    rate_admin DECIMAL(10,2) DEFAULT 70,
                    rate_guard DECIMAL(10,2) DEFAULT 433,
                    rate_sa DECIMAL(10,2) DEFAULT 100,
                    subjects_shs TEXT[],
                    subjects_college TEXT[],
                    sss VARCHAR(20),
                    philhealth VARCHAR(20),
                    pagibig VARCHAR(20),
                    tin VARCHAR(20),
                    emergency_name VARCHAR(120),
                    emergency_relation VARCHAR(50),
                    emergency_phone VARCHAR(20),
                    status VARCHAR(20) DEFAULT 'Active',
                    archived_at TIMESTAMP,
                    archived_reason TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        },
        {
            name: 'teacher_loads',
            sql: `
                CREATE TABLE IF NOT EXISTS teacher_loads (
                    id SERIAL PRIMARY KEY,
                    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                    semester VARCHAR(20) DEFAULT '1st Sem',
                    school_year VARCHAR(20) DEFAULT '2025-2026',
                    subject VARCHAR(100),
                    rate DECIMAL(10,2) DEFAULT 0,
                    mon DECIMAL(5,2) DEFAULT 0,
                    tue DECIMAL(5,2) DEFAULT 0,
                    wed DECIMAL(5,2) DEFAULT 0,
                    thu DECIMAL(5,2) DEFAULT 0,
                    fri DECIMAL(5,2) DEFAULT 0,
                    sat DECIMAL(5,2) DEFAULT 0,
                    sun DECIMAL(5,2) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(employee_id, semester, school_year)
                );
            `
        },
        {
            name: 'attendance',
            sql: `
                CREATE TABLE IF NOT EXISTS attendance (
                    id SERIAL PRIMARY KEY,
                    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                    tab_type VARCHAR(50) DEFAULT 'eda',
                    date DATE NOT NULL,
                    period_start DATE,
                    period_end DATE,
                    payroll_period VARCHAR(50),
                    mon DECIMAL(5,2) DEFAULT 0,
                    tue DECIMAL(5,2) DEFAULT 0,
                    wed DECIMAL(5,2) DEFAULT 0,
                    thu DECIMAL(5,2) DEFAULT 0,
                    fri DECIMAL(5,2) DEFAULT 0,
                    sat DECIMAL(5,2) DEFAULT 0,
                    sun DECIMAL(5,2) DEFAULT 0,
                    hours_worked DECIMAL(5,2) DEFAULT 0,
                    overtime DECIMAL(5,2) DEFAULT 0,
                    lates DECIMAL(5,2) DEFAULT 0,
                    absences INTEGER DEFAULT 0,
                    pay_type VARCHAR(20) DEFAULT 'regular',
                    admin_pay_rate DECIMAL(10,2) DEFAULT 0,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        },
        {
            name: 'payroll',
            sql: `
                CREATE TABLE IF NOT EXISTS payroll (
                    id SERIAL PRIMARY KEY,
                    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
                    period VARCHAR(50) NOT NULL,
                    period_start DATE,
                    period_end DATE,
                    regular_hours DECIMAL(8,2) DEFAULT 0,
                    overtime_hours DECIMAL(8,2) DEFAULT 0,
                    admin_pay DECIMAL(10,2) DEFAULT 0,
                    gross_salary DECIMAL(12,2) DEFAULT 0,
                    sss DECIMAL(10,2) DEFAULT 0,
                    philhealth DECIMAL(10,2) DEFAULT 0,
                    pagibig DECIMAL(10,2) DEFAULT 0,
                    withholding_tax DECIMAL(10,2) DEFAULT 0,
                    undertime_deduction DECIMAL(10,2) DEFAULT 0,
                    sss_loan DECIMAL(10,2) DEFAULT 0,
                    hdmf_loan DECIMAL(10,2) DEFAULT 0,
                    cash_advance DECIMAL(10,2) DEFAULT 0,
                    atm_deposit DECIMAL(10,2) DEFAULT 0,
                    transpo_allowance DECIMAL(10,2) DEFAULT 0,
                    marketing_allowance DECIMAL(10,2) DEFAULT 0,
                    total_deduction DECIMAL(12,2) DEFAULT 0,
                    net_salary DECIMAL(12,2) DEFAULT 0,
                    status VARCHAR(20) DEFAULT 'Pending',
                    approved BOOLEAN DEFAULT FALSE,
                    approved_at TIMESTAMP,
                    approved_by VARCHAR(50),
                    rejection_reason TEXT,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        },
        {
            name: 'notifications',
            sql: `
                CREATE TABLE IF NOT EXISTS notifications (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(200),
                    message TEXT NOT NULL,
                    data JSONB,
                    read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        },
        {
            name: 'settings',
            sql: `
                CREATE TABLE IF NOT EXISTS settings (
                    key VARCHAR(100) PRIMARY KEY,
                    value JSONB,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        },
        {
            name: 'period_settings',
            sql: `
                CREATE TABLE IF NOT EXISTS period_settings (
                    id SERIAL PRIMARY KEY,
                    current_period_start DATE NOT NULL,
                    current_period_end DATE NOT NULL,
                    updated_by INT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            `
        }
    ];

    for (const table of tables) {
        try {
            await pool.query(table.sql);
            console.log(`  ✅ ${table.name}`);
        } catch (err) {
            console.error(`  ❌ ${table.name}: ${err.message}`);
        }
    }
}

async function createIndexes() {
    console.log('\n📊 Creating indexes...');
    
    const indexes = [
        { name: 'idx_attendance_employee_id', sql: 'CREATE INDEX IF NOT EXISTS idx_attendance_employee_id ON attendance(employee_id);' },
        { name: 'idx_attendance_date', sql: 'CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);' },
        { name: 'idx_payroll_employee_id', sql: 'CREATE INDEX IF NOT EXISTS idx_payroll_employee_id ON payroll(employee_id);' },
        { name: 'idx_payroll_period', sql: 'CREATE INDEX IF NOT EXISTS idx_payroll_period ON payroll(period);' },
        { name: 'idx_payroll_status', sql: 'CREATE INDEX IF NOT EXISTS idx_payroll_status ON payroll(status);' },
        { name: 'idx_sessions_token', sql: 'CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);' },
        { name: 'idx_sessions_expires_at', sql: 'CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at);' },
        { name: 'idx_period_settings_dates', sql: 'CREATE INDEX IF NOT EXISTS idx_period_settings_dates ON period_settings(current_period_start, current_period_end);' },
        { name: 'idx_period_settings_updated', sql: 'CREATE INDEX IF NOT EXISTS idx_period_settings_updated ON period_settings(updated_at DESC);' }
    ];

    for (const idx of indexes) {
        try {
            await pool.query(idx.sql);
            console.log(`  ✅ ${idx.name}`);
        } catch (err) {
            // Index already exists is not an error
            if (!err.message.includes('already exists')) {
                console.error(`  ❌ ${idx.name}: ${err.message}`);
            }
        }
    }
}

async function seedInitialData() {
    console.log('\n🌱 Seeding initial data...');

    try {
        // Check if users already exist
        const result = await pool.query('SELECT COUNT(*) FROM users');
        const userCount = parseInt(result.rows[0].count);

        if (userCount === 0) {
            console.log('  Creating default users...');
            
            // Hash passwords
            const accountantHash = await bcrypt.hash('admin123', 10);
            const superadminHash = await bcrypt.hash('superadmin123', 10);
            const oicHash = await bcrypt.hash('oic123', 10);

            await pool.query(
                `INSERT INTO users (username, password_hash, full_name, role, status, email) 
                 VALUES ($1, $2, $3, $4, $5, $6), 
                        ($7, $8, $9, $10, $11, $12),
                        ($13, $14, $15, $16, $17, $18)
                 ON CONFLICT (username) DO NOTHING`,
                [
                    'accountant', accountantHash, 'School Accountant', 'accountant', 'Active', 'accountant@philtech.edu',
                    'superadmin', superadminHash, 'Super Administrator', 'superadmin', 'Active', 'admin@philtech.edu',
                    'oic', oicHash, 'OIC Head', 'oic', 'Active', 'oic@philtech.edu'
                ]
            );
            console.log('  ✅ Default users created');
            console.log('     - accountant / admin123');
            console.log('     - superadmin / superadmin123');
            console.log('     - oic / oic123');
        } else {
            console.log(`  ℹ️  ${userCount} user(s) already exist, skipping seed`);
        }

        // Seed default settings
        const settingsResult = await pool.query('SELECT COUNT(*) FROM settings');
        const settingsCount = parseInt(settingsResult.rows[0].count);

        if (settingsCount === 0) {
            console.log('  Creating default settings...');
            
            await pool.query(
                `INSERT INTO settings (key, value) VALUES 
                 ($1, $2), ($3, $4), ($5, $6), ($7, $8)
                 ON CONFLICT (key) DO NOTHING`,
                [
                    'system', JSON.stringify({ currency: '₱', dateFormat: 'MM/DD/YYYY', payPeriod: 'monthly', overtimeRate: '1.25' }),
                    'tax', JSON.stringify({ taxMethod: 'philippines', taxRate: '20', minimumTax: '0' }),
                    'deduction', JSON.stringify({ sssRate: '4.5', sssMax: '900', philhealthRate: '3', philhealthMax: '300', pagibigRate: '2', pagibigMax: '100' }),
                    'company', JSON.stringify({ companyName: 'Philtech GMA', companyAddress: 'General Mariano Alvarez, Cavite', companyPhone: '(046) 123-4567', companyEmail: 'info@philtech.edu', companyTIN: '123-456-789' })
                ]
            );
            console.log('  ✅ Default settings created');
        }

    } catch (err) {
        console.error('  ❌ Error seeding data:', err.message);
    }
}

// ========== MAIN EXECUTION ==========
async function main() {
    console.log('╔════════════════════════════════════════╗');
    console.log('║   Payroll System Database Initializer  ║');
    console.log('╚════════════════════════════════════════╝');

    try {
        // Test connection
        console.log('\n🔌 Testing database connection...');
        const testQuery = await pool.query('SELECT NOW()');
        console.log(`  ✅ Connected at: ${testQuery.rows[0].now}`);

        // Run migrations
        await createTables();
        await createIndexes();
        await seedInitialData();

        console.log('\n✨ Database initialization complete!');
        console.log('\n📝 Next steps:');
        console.log('   1. Update employee data if needed');
        console.log('   2. Create additional users via the admin panel');
        console.log('   3. Start the server: npm start\n');

    } catch (err) {
        console.error('\n❌ FATAL ERROR:', err.message);
        process.exit(1);
    } finally {
        await pool.end();
    }
}

// Run the script
main();
