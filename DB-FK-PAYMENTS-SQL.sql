-- DBA-ready SQL to add foreign key fk_payments_user_id on payments.user_id -> users.id
-- Pre-requirements: verify all payments.user_id values are NULL or reference existing users.id
-- Run these checks first:

-- 1) Count payments with non-null user_id that do not match any users.id
SELECT COUNT(*) AS orphan_count
FROM payments p
LEFT JOIN users u ON p.user_id = u.id
WHERE p.user_id IS NOT NULL AND u.id IS NULL;

-- 2) Count remaining payments with user_id NULL (optional, audit)
SELECT COUNT(*) AS missing_user_id FROM payments WHERE user_id IS NULL;

-- If orphan_count = 0, proceed to add FK. Use the statement below.

ALTER TABLE payments
ADD CONSTRAINT fk_payments_user_id
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- To drop the FK (rollback):
-- ALTER TABLE payments DROP FOREIGN KEY fk_payments_user_id;
