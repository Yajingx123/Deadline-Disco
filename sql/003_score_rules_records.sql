-- 加分规则和记录管理表
SET SQL_SAFE_UPDATES = 0;
USE acadbeat;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- 加分规则表
DROP TABLE IF EXISTS score_rules;
CREATE TABLE score_rules (
    rule_id VARCHAR(50) PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    rule_type VARCHAR(20) NOT NULL, -- routine: 日常规则, compete: 竞赛规则
    description TEXT NOT NULL,
    base_score INT NOT NULL, -- 基础分数
    daily_limit INT, -- 每日上限分数
    daily_count_limit INT, -- 每日次数上限
    weekly_count_limit INT, -- 每周次数上限
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 加分记录表
DROP TABLE IF EXISTS score_records;
CREATE TABLE score_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    rule_id VARCHAR(50) NOT NULL,
    score INT NOT NULL,
    record_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT, -- 相关用户ID（可选）
    description VARCHAR(255), -- 相关描述（可选）
    FOREIGN KEY (rule_id) REFERENCES score_rules(rule_id)
);

-- 插入日常积分规则
INSERT INTO score_rules (rule_id, rule_name, rule_type, description, base_score, daily_limit, daily_count_limit, weekly_count_limit)
VALUES 
('routine1', '小组日常签到', 'routine', '小组当日签到人数 ≥ 小组总人数的 1/2，获得固定签到分，每日仅算1次', 5, 5, 1, NULL),
('routine2', '在线学习时长', 'routine', '每累计在线学习满5分钟加分，最多计算15分钟（3段）', 3, 9, 3, NULL),
('routine3', '发帖得分', 'routine', '发帖审核通过加分，每日有上限', 3, 3, 1, NULL),
('routine4', '回帖得分', 'routine', '回帖审核通过加分，每日有上限', 1, 1, 1, NULL),
('compete1_win', '周赛Scrabble-获胜', 'compete', '周赛Scrabble小组对战获胜，获得高额积分', 10, NULL, NULL, 1),
('compete1_lose', '周赛Scrabble-参与', 'compete', '周赛Scrabble小组对战失败，获得参与积分', 2, NULL, NULL, 1);

-- 创建索引以提高查询性能
CREATE INDEX idx_score_records_group_id ON score_records(group_id);
CREATE INDEX idx_score_records_rule_id ON score_records(rule_id);
CREATE INDEX idx_score_records_record_time ON score_records(record_time);
