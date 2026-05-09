# Production DB Session Migration (DB-backed PHP sessions)

This repo uses a DB-backed PHP session handler. To prevent Render/local schema drift and login/session 500s, apply migrations using the built-in migration system.

## 0) What changed
- `api/core/Session/DatabaseSessionHandler.php` stores PHP session payload in the `sessions` table.
- A new safe migration kernel was added:
  - `api/core/Migration/Migrator.php`
  - `api/migrate.php`
- Boot-time migrations are optionally enabled via env var:
  - `RUN_MIGRATIONS=1`

## 1) Run migrations manually (recommended after deploy)
1. Deploy/redeploy the app code.
2. Call migrations endpoint once (or run it in your CI):

   **GET** `/api/migrate.php`

3. Verify response JSON contains:
   - `success: true`
   - `migrations_completed: true`

## 2) Enable auto-migrations at boot (NOT recommended)
This repo intentionally **does not** run migrations during request bootstrap. Run `/api/migrate.php` once during deploy instead.


## 3) What migrations do (safe + idempotent)
Current migrations ensure:
- `sessions` table exists with columns:
  - `id` (VARCHAR PK)
  - `payload`
  - `last_activity`
  - `created_at`
- Missing session columns are added safely using `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`.
- No `DROP TABLE` is performed by the migration system.

## 4) Troubleshooting login/session 500
If `/api/auth/login.php` or `/api/auth/session.php` still returns 500:

1. Check logs and look for DB errors about missing columns (e.g. `last_activity`).
2. Re-run migrations endpoint:
   - `GET /api/migrate.php`

## 5) Old system: do NOT use
Avoid relying on:
- `api/run-schema.php` + `api/config/schema.sql` re-runs as a production mechanism.

The new migration kernel is the production-safe approach.

