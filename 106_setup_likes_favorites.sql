-- Forum likes and favorites tables
USE acadbeat;
SET NAMES utf8mb4;

SET @base_tables_ready = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN ('users', 'forum_posts')
);

-- Forum post likes table
SET @preparedStatement = IF(
    @base_tables_ready = 2,
    'CREATE TABLE IF NOT EXISTS forum_post_likes (
        like_id INT AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_post_like (post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES forum_posts(post_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_post_id (post_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT ''Skip forum_post_likes: users/forum_posts table missing'''
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Forum post favorites table
SET @preparedStatement = IF(
    @base_tables_ready = 2,
    'CREATE TABLE IF NOT EXISTS forum_post_favorites (
        favorite_id INT AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_post_favorite (post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES forum_posts(post_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_post_id (post_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT ''Skip forum_post_favorites: users/forum_posts table missing'''
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add like_count and favorite_count columns to forum_posts table
SET @dbname = DATABASE();
SET @tablename = 'forum_posts';
SET @columnname1 = 'like_count';
SET @columnname2 = 'favorite_count';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
  ) = 0,
  'SELECT 1',
  IF(
    (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname1)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname1, ' INT DEFAULT 0 AFTER comment_count')
  )
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
  ) = 0,
  'SELECT 1',
  IF(
    (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_schema = @dbname)
      AND (table_name = @tablename)
      AND (column_name = @columnname2)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname2, ' INT DEFAULT 0 AFTER like_count')
  )
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
