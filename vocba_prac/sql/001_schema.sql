-- Vocabulary module – 数据库结构
-- 词书、单词、多对多关联（单词可属于多个词书）

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 词书表
-- ----------------------------
DROP TABLE IF EXISTS `word_book_words`;
DROP TABLE IF EXISTS `words`;
DROP TABLE IF EXISTS `word_books`;

CREATE TABLE `word_books` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(32) NOT NULL COMMENT '唯一标识：daily, cs, mech, civil, traffic, math',
  `title` varchar(128) NOT NULL,
  `description` text COMMENT '词书简介',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='词书';

-- ----------------------------
-- 单词表（全局唯一，可被多个词书引用）
-- ----------------------------
CREATE TABLE `words` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `word` varchar(64) NOT NULL COMMENT '单词',
  `phonetic` varchar(128) DEFAULT NULL COMMENT '音标',
  `meaning` varchar(512) NOT NULL COMMENT '释义',
  `sentence` varchar(512) DEFAULT NULL COMMENT '例句',
  `image_url` varchar(512) DEFAULT NULL COMMENT '图片路径或URL',
  `audio_url` varchar(512) DEFAULT NULL COMMENT '发音音频路径或URL',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='单词';

-- ----------------------------
-- 词书-单词关联表（多对多，支持重叠）
-- ----------------------------
CREATE TABLE `word_book_words` (
  `word_book_id` int unsigned NOT NULL,
  `word_id` int unsigned NOT NULL,
  `sort_order` int unsigned DEFAULT 0 COMMENT '词书内排序',
  PRIMARY KEY (`word_book_id`, `word_id`),
  KEY `idx_word_id` (`word_id`),
  CONSTRAINT `fk_wbw_book` FOREIGN KEY (`word_book_id`) REFERENCES `word_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wbw_word` FOREIGN KEY (`word_id`) REFERENCES `words` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='词书-单词关联';

SET FOREIGN_KEY_CHECKS = 1;
