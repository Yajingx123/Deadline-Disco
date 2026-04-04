-- 公告表（与 forum-project/api/announcements.php 一致）
-- 在 acadbeat 库中执行：mysql -u... -p acadbeat < sql/220_forum_announcements.sql

CREATE TABLE IF NOT EXISTS forum_announcements (
  announcement_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(512) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id),
  KEY idx_forum_announcements_active_pinned_created (is_active, is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
