// server.js — Node/Express static file server + DB init helper
//
// NOTE: The primary API backend is PHP (router.php + api/ folder).
// This Node server is used ONLY for:
//   1. Local static file serving during development
//   2. Running `npm run db:init` equivalent via initializeDatabase()
//   3. Health check endpoint
//
// All auth endpoints are handled by PHP in api/auth/.
// Do NOT add conflicting /api/* routes here.

require('dotenv').config();

const express = require('express');
const path = require('path');
const cors = require('cors');
const { Pool } = require('pg');
const bcrypt = require('bcryptjs');

const app = express();
const PORT = process.env.PORT || 10000;
const HOST = '0.0.0.0';

// ============================================
// PostgreSQL connection helper
// ============================================
function getPostgresConnectionString() {
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

    return null;
}

const databaseUrl = getPostgresConnectionString();
if (!databaseUrl) {
    console.error('❌ PostgreSQL connection not configured. Set DATABASE_URL or PGHOST/PGUSER/PGPASSWORD/PGDATABASE.');
    process.exit(1);
}

const pool = new Pool({
    connectionString: databaseUrl,
    ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ============================================
// No-cache middleware for HTML files
// ============================================
app.use((req, res, next) => {
    if (req.path.match(/\.(html|htm)$/i) || req.path === '/') {
        res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        res.setHeader('Pragma', 'no-cache');
        res.setHeader('Expires', '0');
    }
    next();
});

// ============================================
// Database Initialization (for local dev use)
// ============================================
async function initializeDatabase() {
    try {
        console.log('🔄 Initializing PostgreSQL database...');

        // Create users table
        await pool.query(`
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(120),
                email VARCHAR(120),
                role VARCHAR(20) DEFAULT 'teacher',
                status VARCHAR(20) DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);
        console.log('✅ Users table ready');

        // Create employees table
        await pool.query(`
            CREATE TABLE IF NOT EXISTS employees (
                id SERIAL PRIMARY KEY,
                employee_id VARCHAR(20) UNIQUE,
                full_name VARCHAR(120) NOT NULL,
                position VARCHAR(80),
                department VARCHAR(80),
                employment_type VARCHAR(20) DEFAULT 'Regular',
                base_salary DECIMAL(10,2) DEFAULT 0,
                email VARCHAR(120),
                phone VARCHAR(20),
                status VARCHAR(20) DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);
        console.log('✅ Employees table ready');

        // Check if default users exist
        const result = await pool.query('SELECT COUNT(*) FROM users');
        if (parseInt(result.rows[0].count) === 0) {
            console.log('👤 Creating default PostgreSQL users...');
            const salt = await bcrypt.genSalt(10);

            const adminHash = await bcrypt.hash('admin123', salt);
            const superHash = await bcrypt.hash('superadmin123', salt);

            await pool.query(`
                INSERT INTO users (username, password_hash, full_name, role) VALUES
                ($1, $2, $3, $4),
                ($5, $6, $7, $8)
            `, [
                'accountant', adminHash, 'School Accountant', 'accountant',
                'superadmin', superHash, 'Super Admin', 'superadmin'
            ]);
            console.log('✅ Default users created (accountant & superadmin)');
        }

        // Create sample employee if none exist
        const empResult = await pool.query('SELECT COUNT(*) FROM employees');
        if (parseInt(empResult.rows[0].count) === 0) {
            await pool.query(`
                INSERT INTO employees (employee_id, full_name, position, department, employment_type, base_salary, email, status)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
            `, [
                'TCH001', 'Sample Teacher', 'Senior Teacher', 'Academic', 'Regular', 25000, 'teacher@philtech.edu', 'Active'
            ]);
            console.log('✅ Sample employee created');
        }

    } catch (err) {
        console.error('❌ Database initialization error:', err);
    }
}

// ============================================
// Health check endpoint
// ============================================
app.get('/api/health', (req, res) => {
    res.json({
        status: 'ok',
        message: 'Node server is running (PHP handles API routes)',
        mode: 'static-file-server'
    });
});

// ============================================
// Serve static files
// ============================================
app.use(express.static(__dirname));

// ============================================
// Catch-all for frontend routes (SPA fallback)
// ============================================
app.get('*', (req, res) => {
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    res.setHeader('Pragma', 'no-cache');
    res.setHeader('Expires', '0');
    res.sendFile(path.join(__dirname, 'index.html'));
});

// ============================================
// Start server
// ============================================
app.listen(PORT, HOST, async () => {
    console.log(`✅ Node server running at http://${HOST}:${PORT}/`);
    console.log(`📝 NOTE: API routes are served by PHP (router.php), not Node.`);
    console.log(`📁 Serving static files from: ${__dirname}`);

    await initializeDatabase();
});
