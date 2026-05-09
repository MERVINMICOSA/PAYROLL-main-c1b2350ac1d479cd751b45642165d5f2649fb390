# Payroll System Setup Guide

## Prerequisites
- Node.js 18.x or higher
- PostgreSQL (for local development) or Render PostgreSQL (for production)
- npm or yarn package manager

---

## Local Development Setup

### 1. **Clone & Install Dependencies**
```bash
npm install
```

### 2. **Configure Database Connection**
Create a `.env` file from the template:
```bash
cp .env.example .env
```

#### Option A: Local PostgreSQL (Recommended for Development)
Edit `.env`:
```env
PGHOST=localhost
PGPORT=5432
PGUSER=postgres
PGPASSWORD=your_password
PGDATABASE=payroll_db
NODE_ENV=development
```

**Ensure PostgreSQL is running:**
- Windows: Start PostgreSQL service or use pgAdmin
- Mac: `brew services start postgresql`
- Linux: `sudo service postgresql start`

**Create the payroll_db:**
```bash
psql -U postgres -c "CREATE DATABASE payroll_db;"
```

#### Option B: Render PostgreSQL (Production)
Get your `DATABASE_URL` from [Render Dashboard](https://dashboard.render.com):
1. Create a new PostgreSQL database
2. Copy the connection string
3. Paste into `.env`:
```env
DATABASE_URL=postgresql://user:password@host:port/payroll_db
NODE_ENV=production
```

---

## 3. **Initialize Database**

Run the database initialization script:
```bash
npm run db:init
```

**What this does:**
- ✅ Creates all required tables (users, employees, attendance, payroll, etc.)
- ✅ Creates indexes for performance optimization
- ✅ Seeds default users and settings
- ✅ Exits gracefully if tables already exist

**Default Users Created:**
| Username | Password | Role |
|----------|----------|------|
| accountant | admin123 | accountant |
| superadmin | superadmin123 | superadmin |
| oic | oic123 | oic |

---

## 4. **Start the Server**

### Development (with auto-reload):
```bash
npm run dev
```

### Production:
```bash
npm start
```

Server runs on: **http://localhost:10000**

---

## Rendering/Deployment to Render

### 1. **Push Code to GitHub**
```bash
git add .
git commit -m "Initial setup"
git push origin main
```

### 2. **Create Render PostgreSQL Database**
1. Go to [Render Dashboard](https://dashboard.render.com)
2. Click **+ New** → **PostgreSQL**
3. Name: `payroll-db`
4. Region: Choose closest to users
5. Create database
6. Copy the `DATABASE_URL`

### 3. **Create Render Web Service**
1. Click **+ New** → **Web Service**
2. Connect your GitHub repository
3. Set build command: `npm install`
4. Set start command: `npm start`
5. Add environment variable:
   - Key: `DATABASE_URL`
   - Value: Paste the PostgreSQL connection string
6. Deploy

### 4. **Initialize Database on Render**
After deployment completes, open a bash console or SSH into the Render service:
```bash
npm run db:init
```

Or access the initialization endpoint:
```
https://your-render-domain.com/api/migrate-database.php
```

---

## Troubleshooting

### "SQLSTATE[42P01]: Undefined table: 7 ERROR: relation 'users' does not exist"
**Solution:** Run database initialization:
```bash
npm run db:init
```

### Connection refused (ECONNREFUSED 127.0.0.1:5432)
**Solution:** 
- PostgreSQL is not running
- Check connection string in `.env`
- Verify PGHOST, PGUSER, PGPASSWORD

### Database already exists error
**Solution:** Tables are idempotent (use `IF NOT EXISTS`), safe to re-run:
```bash
npm run db:init
```

### "FATAL: remaining 'oid' error" in Render
**Solution:**
1. Drop and recreate PostgreSQL instance in Render
2. Re-run `npm run db:init`

---

## File Structure

```
project/
├── server.js                    # Main Node.js server
├── .env                         # Configuration (don't commit)
├── .env.example                 # Configuration template
├── package.json                 # Dependencies
├── scripts/
│   └── init-db.js              # Database initialization script
├── api/
│   ├── config/
│   │   ├── database.php         # PHP database config
│   │   └── schema.sql           # SQL schema reference
│   ├── auth/                    # Authentication endpoints
│   ├── models/                  # Database models
│   └── ...
├── js/                          # Frontend JavaScript
└── css/                         # Styling
```

---

## Next Steps

1. ✅ Database initialized
2. 🔐 Test login with default credentials
3. 👥 Create employees and users via admin panel
4. 📊 Configure payroll periods and settings
5. 🎓 Set up teacher loads and assignments
6. 📝 Begin tracking attendance

---

## Support

For issues or questions:
1. Check browser console for frontend errors
2. Check server logs for backend errors
3. Verify `.env` configuration
4. Confirm database connection works: `npm run db:init`

