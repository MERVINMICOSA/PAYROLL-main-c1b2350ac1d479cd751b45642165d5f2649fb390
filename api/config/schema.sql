-- Philtech GMA Payroll System Database Schema
-- Run this on your PostgreSQL database (Render or local)

-- Users table
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

-- Sessions table for token-based authentication
CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT
);

-- Employees table
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

-- Teacher Loads table
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

-- Attendance table
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

-- Payroll table
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

-- Notifications table
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

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(100) PRIMARY KEY,
    value JSONB,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Period Settings table (supports multiple payroll periods)
CREATE TABLE IF NOT EXISTS period_settings (
    id SERIAL PRIMARY KEY,
    current_period_start DATE NOT NULL,
    current_period_end DATE NOT NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance Periods table (no overlapping periods allowed)
CREATE TABLE IF NOT EXISTS attendance_periods (
    id SERIAL PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    description VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(period_start, period_end),
    CONSTRAINT valid_period CHECK (period_start < period_end)
);

-- Create indexes for performance
CREATE INDEX idx_attendance_employee_id ON attendance(employee_id);
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_attendance_period ON attendance(period_start, period_end);
CREATE INDEX idx_payroll_employee_id ON payroll(employee_id);
CREATE INDEX idx_payroll_period ON payroll(period);
CREATE INDEX idx_payroll_status ON payroll(status);
CREATE INDEX idx_sessions_token ON sessions(token);
CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);
CREATE INDEX idx_period_settings_dates ON period_settings(current_period_start, current_period_end);
CREATE INDEX idx_period_settings_updated ON period_settings(updated_at DESC);
CREATE INDEX idx_attendance_periods_dates ON attendance_periods(period_start, period_end);
CREATE INDEX idx_attendance_periods_status ON attendance_periods(status);

-- Insert default settings
INSERT INTO settings (key, value) VALUES 
('system', '{"currency":"₱","dateFormat":"MM/DD/YYYY","payPeriod":"monthly","overtimeRate":"1.25"}'::jsonb),
('tax', '{"taxMethod":"philippines","taxRate":"20","minimumTax":"0"}'::jsonb),
('deduction', '{"sssRate":"4.5","sssMax":"900","philhealthRate":"3","philhealthMax":"300","pagibigRate":"2","pagibigMax":"100"}'::jsonb),
('company', '{"companyName":"Philtech GMA","companyAddress":"General Mariano Alvarez, Cavite","companyPhone":"(046) 123-4567","companyEmail":"info@philtech.edu","companyTIN":"123-456-789"}'::jsonb)
ON CONFLICT (key) DO NOTHING;

-- Add missing columns to existing tables (idempotent)
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS read BOOLEAN DEFAULT FALSE;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(200);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Insert default users (passwords are hashed with bcrypt)
-- Default password for accountant: admin123
-- Default password for superadmin: superadmin123
INSERT INTO users (username, password_hash, full_name, role, status) VALUES 
('accountant', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mr4qZ5q8q5q8q5q8q5q8q5q8q5q8q5', 'School Accountant', 'accountant', 'Active'),
('superadmin', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mr4qZ5q8q5q8q5q8q5q8q5q8q5q8q5', 'Super Administrator', 'superadmin', 'Active'),
('oic', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mr4qZ5q8q5q8q5q8q5q8q5q8q5q8q5', 'OIC Head', 'oic', 'Active')
ON CONFLICT (username) DO NOTHING;