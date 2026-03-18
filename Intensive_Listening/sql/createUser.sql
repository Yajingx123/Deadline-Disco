-- 1. 创建用户表（适配你的音频项目，预留扩展字段）
CREATE TABLE IF NOT EXISTS `intensive_listening_user` (
    `user_id` INT PRIMARY KEY AUTO_INCREMENT COMMENT '用户唯一ID（自增主键）',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（建议加密存储，这里示例用简单值）',
    `email` VARCHAR(100) UNIQUE COMMENT '邮箱（唯一，可选）',
    `create_time` DATETIME DEFAULT NOW() COMMENT '创建时间'
) COMMENT = '用户信息表';

-- 3. 插入2个测试用户
INSERT INTO `intensive_listening_user` (username, password, email)
VALUES
('user1', '123456', 'user1@example.com'),  -- 第一个用户：用户名user1，密码123456
('user2', '654321', 'user2@example.com');  -- 第二个用户：用户名user2，密码654321

