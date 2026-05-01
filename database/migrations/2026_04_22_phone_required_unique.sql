USE verivote;

-- Normalize obvious formatting variants before hardening constraints.
UPDATE users SET phone = TRIM(phone);
UPDATE users SET phone = CONCAT('+', SUBSTRING(phone, 3)) WHERE phone LIKE '00%';
UPDATE users SET phone = REPLACE(phone, ' ', '');
UPDATE users SET phone = REPLACE(phone, '-', '');
UPDATE users SET phone = REPLACE(phone, '(', '');
UPDATE users SET phone = REPLACE(phone, ')', '');

-- Review and fix any rows returned by the checks below before running ALTER TABLE.
SELECT id, email, phone FROM users WHERE phone IS NULL OR phone = '';
SELECT phone, COUNT(*) AS duplicates FROM users GROUP BY phone HAVING COUNT(*) > 1;

ALTER TABLE users
    MODIFY phone VARCHAR(40) NOT NULL;

ALTER TABLE users
    ADD UNIQUE KEY uniq_users_phone (phone);
