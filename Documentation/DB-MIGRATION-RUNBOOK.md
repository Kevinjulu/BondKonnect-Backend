# Runbook: Backfill `payments.user_id` and Add FK

This runbook describes safe steps to run the migrations and backfill `payments.user_id` in staging and production.

IMPORTANT: Test all steps in staging first and take full DB backups before applying to production.

## Preflight (staging)

- Ensure code changes are deployed (this branch) which includes:
  - migrations: `2026_02_14_000001_add_portal_id_to_users.php`, `2026_02_14_000002_add_user_id_to_payments_and_change_log.php`
  - artisan command: `bk:backfill-payments-user-id`
- Take logical + physical backups of both DBs (`bk_api_db` and `bk_db`).
- Put app into maintenance mode (or disable write traffic):

```bash
php artisan down --message="DB maintenance"
```

## Step 1 — Run migrations (staging)

```bash
php artisan migrate --path=database/migrations/2026_02_14_000001_add_portal_id_to_users.php
php artisan migrate --path=database/migrations/2026_02_14_000002_add_user_id_to_payments_and_change_log.php
```

Or run all pending migrations:

```bash
php artisan migrate
```

Confirm columns exist:

```sql
-- run on bk_api_db
SHOW COLUMNS FROM users LIKE 'portal_id';
SHOW COLUMNS FROM payments LIKE 'user_id';
SELECT COUNT(*) FROM payment_user_backfill_log;
```

## Step 2 — Dry-run backfill

Run the artisan command in dry-run mode (default):

```bash
php artisan bk:backfill-payments-user-id
```

This prints sample payments missing `user_id` and counts. Review the sample output.

## Step 3 — Analyze unmatched records

Run these SQL queries (on `bk_api_db`) to understand outstanding cases:

```sql
-- Payments lacking user match
SELECT COUNT(*) AS missing FROM payments p WHERE p.user_id IS NULL;

-- Payments lacking user and with non-empty email
SELECT COUNT(*) AS missing_with_email FROM payments p WHERE p.user_id IS NULL AND COALESCE(TRIM(p.user_email),'') <> '';

-- Users that match portal user by email (case-insensitive)
SELECT COUNT(*) FROM bk_db.portaluserlogoninfo p
JOIN bk_api_db.users u ON LOWER(TRIM(p.Email)) = LOWER(TRIM(u.email));

-- Portal users without corresponding local users
SELECT p.Id, p.Email FROM bk_db.portaluserlogoninfo p
LEFT JOIN bk_api_db.users u ON LOWER(TRIM(p.Email)) = LOWER(TRIM(u.email))
WHERE u.id IS NULL
LIMIT 100;

-- Payments with email but no local user
SELECT p.id, p.user_email FROM payments p
LEFT JOIN users u ON LOWER(TRIM(p.user_email)) = LOWER(TRIM(u.email))
WHERE u.id IS NULL
LIMIT 100;
```

Decide remediation: create local `users` for unmatched portal users, or correct emails as appropriate.

## Step 4 — Execute backfill (staging)

When ready, run the artisan command with `--execute` (it will log changes to `payment_user_backfill_log`):

```bash
php artisan bk:backfill-payments-user-id --execute --run-id=staging-$(date +%s)
```

Verify results:

```sql
SELECT COUNT(*) FROM payment_user_backfill_log WHERE run_id = 'staging-<timestamp>'; -- use actual run id

-- Sample updated payments
SELECT p.id, p.user_email, p.user_id, u.email AS user_email_from_users
FROM payments p
LEFT JOIN users u ON u.id = p.user_id
WHERE p.user_id IS NOT NULL
ORDER BY p.id DESC
LIMIT 50;

-- Check remaining missing
SELECT COUNT(*) FROM payments WHERE user_id IS NULL;
```

## Step 5 — Add FK constraint (after verification)

Add FK in a reversible migration or run SQL after confirming all user_id values are valid.

Migration SQL example (run only after verification):

```sql
ALTER TABLE payments
ADD CONSTRAINT fk_payments_user_id
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE;
```

If you prefer a Laravel migration, create a migration that runs the above in `up()` and drops the FK in `down()`.

## Step 6 — Post-migration checks

- Run application integration tests (payment flows, webhooks, subscription activation).
- Verify logs for errors.
- Monitor error/exception tracking and slow queries.

## Rollback plan

- If the backfill produced incorrect mappings, revert via `payment_user_backfill_log`:

```sql
-- Example rollback using run_id
UPDATE payments p
JOIN payment_user_backfill_log l ON l.payment_id = p.id
SET p.user_id = l.old_user_id
WHERE l.run_id = '<run_id>';

DELETE FROM payment_user_backfill_log WHERE run_id = '<run_id>';
```

- If FK was added and problems appear, drop the FK:

```sql
ALTER TABLE payments DROP FOREIGN KEY fk_payments_user_id;
```

## Notes & Recommendations

- Keep `user_email` column as audit for at least 30 days after migration.
- Run this process in production during a low-traffic window.
- Ensure DB user used for migrations has necessary privileges but avoid broad superuser access where possible.
