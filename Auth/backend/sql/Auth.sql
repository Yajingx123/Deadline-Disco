-- 1. 创建独立的身份验证数据库 (如果还未创建)
CREATE DATABASE IF NOT EXISTS auth_database 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 2. 切换到该数据库
USE auth_database;

-- 3. 创建用户表 (包含用户名、邮箱和加密密码)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 
    username VARCHAR(50) NOT NULL UNIQUE COMMENT
    email VARCHAR(255) NOT NULL UNIQUE COMMENT '邮箱地址 (必须唯一)',
    password VARCHAR(255) NOT NULL COMMENT '加密后的密码',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
