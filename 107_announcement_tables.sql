USE acadbeat;

-- 创建公告表
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `author` VARCHAR(100) NOT NULL,
  `is_pinned` TINYINT(1) DEFAULT 0,
  `status` ENUM('draft', 'published') DEFAULT 'published',
  `view_count` INT DEFAULT 0
);

-- 创建公告阅读记录表（可选，用于统计阅读量）
CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `announcement_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_announcement` (`user_id`, `announcement_id`)
);

-- 插入示例数据
INSERT INTO `announcements` (`title`, `content`, `author`, `is_pinned`, `status`) VALUES
('Welcome to AcadBeat Forum!', 'Welcome to our new forum platform. Here you can discuss English learning topics, share resources, and connect with other students.', 'Admin', 1, 'published'),
('Forum Rules Update', 'Please read the updated forum rules. We have implemented new guidelines to maintain a positive community environment.', 'Admin', 0, 'published'),
('Upcoming Vocabulary Contest', 'Stay tuned for our upcoming vocabulary contest with exciting prizes!', 'Admin', 0, 'published');
