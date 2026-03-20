CREATE DATABASE IF NOT EXISTS auth_database
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE auth_database;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '用户主键',
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    email VARCHAR(255) NOT NULL UNIQUE COMMENT '邮箱地址（必须唯一）',
    password VARCHAR(255) NOT NULL COMMENT '加密后的密码',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, email, password, created_at)
VALUES
    ('user1', 'user1@example.com', '$2y$12$vnVDT.5TiJY1IMrrwFuhb.ul3Db.axb2wPM/9s16n.2HstjV3N5Da', NOW()),
    ('user2', 'user2@example.com', '$2y$12$vnVDT.5TiJY1IMrrwFuhb.ul3Db.axb2wPM/9s16n.2HstjV3N5Da', NOW())
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password = VALUES(password);
