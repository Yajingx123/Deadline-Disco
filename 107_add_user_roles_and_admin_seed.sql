USE acadbeat;
SET NAMES utf8mb4;

SET @users_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
);

SET @role_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
);

SET @preparedStatement = IF(
    @users_exists = 1 AND @role_column_exists = 0,
    'ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT ''user'' AFTER avatar_url',
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = IF(
    @users_exists = 1,
    'UPDATE users
     SET role = CASE
         WHEN role = ''admin'' THEN ''admin''
         ELSE ''user''
     END',
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = IF(
    @users_exists = 1,
    'ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT ''user''',
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @check_constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'chk_users_role'
);

SET @preparedStatement = IF(
    @users_exists = 1 AND @check_constraint_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN (''user'', ''admin''))',
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = IF(
    @users_exists = 1,
    'INSERT INTO users (username, email, password_hash, avatar_url, role, created_at, updated_at)
     SELECT ''admin'', ''admin@acadbeat.local'', ''$2y$10$m9ZwHKhrmiHMLwzhW98M5eizotJbNAnaJi2Ur7oOW/Rrbf44PhIvS'', NULL, ''admin'', NOW(), NOW()
     WHERE NOT EXISTS (
         SELECT 1
         FROM users
         WHERE username = ''admin'' OR email = ''admin@acadbeat.local''
     )',
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = IF(
    @users_exists = 1,
    'UPDATE users
     SET role = ''admin''
     WHERE username = ''admin'' OR email = ''admin@acadbeat.local''',
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
