# Priority 1 Security Fixes - COMPLETED ✅

## Tasks Completed
- [x] Fix 1: Removed hardcoded Firebase API key from `api/config/firebase-config.php`
- [x] Fix 2: Removed hardcoded Firebase API key from `js/firebase-config.js`
- [x] Fix 3: Removed hardcoded Firebase API key from `js/firebase-auth.js`
- [x] Fix 4: Added `requireRole()` function to `api/middleware/auth.php`
- [x] Fix 5: Added role checks to `api/employees.php`
- [x] Fix 6: Added role checks to `api/payroll.php`
- [x] Fix 7: Added role checks to `api/period/set.php`
- [x] Fix 8: Verified `api/period/get.php` — read-only GET, open to all authenticated users
- [x] Fix 9: Verified `api/period/list.php` — read-only GET, open to all authenticated users
- [x] Fix 10: Fixed `sanitizeForDB()` in `api/middleware/sanitize.php`

## Summary of Changes

### 1. Firebase API Key Security (3 files)
- `api/config/firebase-config.php`: Now loads `FIREBASE_API_KEY` from environment variable. Returns 500 if not configured.
- `js/firebase-config.js`: Fetches config from `/api/config/firebase-config.php` at runtime. No hardcoded key.
- `js/firebase-auth.js`: Fetches config from server API via `fetchFirebaseConfig()`. No hardcoded key.

### 2. Backend Role-Based Access Control (4 files)
- `api/middleware/auth.php`: Added `requireRole(array $allowedRoles)` function. Returns 401 if not logged in, 403 if role insufficient.
- `api/employees.php`: POST/PUT/DELETE restricted to `superadmin` and `accountant`. GET remains open to all authenticated users.
- `api/payroll.php`: POST/PUT/DELETE (including `generate_from_attendance`) restricted to `superadmin` and `accountant`. GET remains open.
- `api/period/set.php`: POST restricted to `superadmin` and `accountant`.

### 3. Sanitization Fix (1 file)
- `api/middleware/sanitize.php`: `sanitizeForDB()` now delegates to `sanitize()` instead of stripping SQL keywords. PDO prepared statements already prevent SQL injection.

## Role Mapping Enforced
| Role | Employees | Payroll | Period (set) |
|------|-----------|---------|--------------|
| superadmin | ✅ CRUD | ✅ CRUD | ✅ Set |
| accountant | ✅ CRUD | ✅ CRUD | ✅ Set |
| oic | ❌ View only | ❌ View only | ❌ View only |
| teacher | ❌ View only | ❌ View only | ❌ View only |
| guard | ❌ View only | ❌ View only | ❌ View only |
| sa | ❌ View only | ❌ View only | ❌ View only |
| admin-staff | ❌ View only | ❌ View only | ❌ View only |

## Environment Variables Required (for Firebase, if used)
```env
FIREBASE_API_KEY=your_actual_api_key_here
FIREBASE_AUTH_DOMAIN=philtech-payroll.firebaseapp.com
FIREBASE_PROJECT_ID=philtech-payroll
FIREBASE_STORAGE_BUCKET=philtech-payroll.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=988193021445
FIREBASE_APP_ID=1:988193021445:web:20553630a83c8db5e8066c
```

## Testing Checklist
- [ ] Log in as `teacher` → try POST to `/api/employees.php` → expect 403 Forbidden
- [ ] Log in as `accountant` → try POST to `/api/employees.php` → expect 200 OK
- [ ] Log in as `superadmin` → try DELETE to `/api/payroll.php?id=1` → expect 200 OK
- [ ] Verify Firebase config endpoint without env var → expect 500 error
- [ ] Verify submitting text with "SELECT" in a form field → text preserved correctly
