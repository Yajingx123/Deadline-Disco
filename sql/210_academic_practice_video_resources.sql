-- =========================================
-- 视频资源管理表 - 用于管理外网服务器上的视频资源
-- =========================================

USE acadbeat;

-- 视频资源主表
CREATE TABLE IF NOT EXISTS video_resources (
    video_id VARCHAR(50) PRIMARY KEY COMMENT '视频唯一标识，如 u1, s1',
    mode VARCHAR(20) NOT NULL COMMENT '模式: understand( Listening and Understand) / respond( Listening and Respond)',
    title VARCHAR(255) NOT NULL COMMENT '视频标题',
    video_type VARCHAR(50) NOT NULL COMMENT '视频类型: Campus, Academic, Study Skills',
    difficulty VARCHAR(20) NOT NULL COMMENT '难度: Easy, Medium, Hard',
    duration VARCHAR(20) NOT NULL COMMENT '时长: 0-1min, 1-2min, 2-3min',
    source VARCHAR(50) NOT NULL COMMENT '来源: ELLLO, OpenLearn',
    country VARCHAR(50) NOT NULL COMMENT '国家',
    author VARCHAR(100) NULL COMMENT '作者名字',
    time_specific VARCHAR(20) NULL COMMENT '具体时间点，如 00:40',
    
    -- 外网服务器上的文件路径
    video_url VARCHAR(500) NOT NULL COMMENT '视频文件完整URL',
    transcript_url VARCHAR(500) NOT NULL COMMENT '转录文本文件URL',
    vtt_url VARCHAR(500) NULL COMMENT '字幕文件URL',
    labels_url VARCHAR(500) NULL COMMENT '标签信息文件URL',
    sample_notes_url VARCHAR(500) NULL COMMENT '示例笔记文件URL',
    cover_url VARCHAR(500) NOT NULL COMMENT '封面图片URL',
    flag_url VARCHAR(500) NOT NULL COMMENT '国旗图片URL',
    
    -- 文本内容（可选，可以存在数据库里也可以只存路径）
    transcript_text TEXT NULL COMMENT '转录文本内容',
    question TEXT NULL COMMENT 'respond模式的问题',
    answer_text TEXT NULL COMMENT '参考答案文本',
    
    -- 状态和管理
    status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '状态: active, inactive',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序顺序',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT chk_video_resources_mode CHECK (mode IN ('understand', 'respond')),
    CONSTRAINT chk_video_resources_difficulty CHECK (difficulty IN ('Easy', 'Medium', 'Hard')),
    CONSTRAINT chk_video_resources_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='视频资源管理表';

-- 创建索引
CREATE INDEX idx_video_resources_mode ON video_resources(mode);
CREATE INDEX idx_video_resources_type ON video_resources(video_type);
CREATE INDEX idx_video_resources_difficulty ON video_resources(difficulty);
CREATE INDEX idx_video_resources_status ON video_resources(status);
CREATE INDEX idx_video_resources_sort ON video_resources(sort_order);

-- =========================================
-- 插入示例数据 - 基于现有的 practice-data.js
-- 注意：需要将 base_url 替换为你的外网服务器地址
-- =========================================

-- 设置基础URL变量（请根据实际情况修改）
-- SET @base_url = 'http://111.231.10.140/media';

-- Listening and Understand 模式视频 (u1-u12)
INSERT INTO video_resources (
    video_id, mode, title, video_type, difficulty, duration, source, country, author, time_specific,
    video_url, transcript_url, vtt_url, labels_url, sample_notes_url, cover_url, flag_url,
    transcript_text, question, answer_text, sort_order
) VALUES 
-- u1
('u1', 'understand', 'What is the secret to learning English?', 'Campus', 'Easy', '0-1min', 'ELLLO', 'Germany', 'Christina', '00:40',
 'http://111.231.10.140/media/material/1.mp4',
 'http://111.231.10.140/media/material/1_transcript.txt',
 'http://111.231.10.140/media/material/1.vtt',
 'http://111.231.10.140/media/material/1_labels.txt',
 'http://111.231.10.140/media/material/1_sample_notes.txt',
 'http://111.231.10.140/media/cover/1.png',
 'http://111.231.10.140/media/flags/flags/de.png',
 'Hi, my name is Christina and I\'m from Germany. My question is what is the best way to learn English?\n\nWell, I was really lucky because I lived abroad in New Zealand for one year, so I\'ve spoken a lot of English there, and I really improved my English but what is really really important before you do that is learn some basics, so I learned the basics at school with a lot of listenings and readings, so exactly what those kind of videos are for, so I think it\'s a good combination if your first do the basic stuff and then just go over there and try to speak English all the time.',
 NULL,
 'The speaker introduces library zones, borrowing rules, and where students can get research support.',
 1),

-- u2
('u2', 'understand', 'Joining a Study Group', 'Study Skills', 'Medium', '2-3min', 'ELLLO', 'US', NULL, NULL,
 'http://111.231.10.140/media/material/study_group.mp4',
 'http://111.231.10.140/media/material/study_group_transcript.txt',
 'http://111.231.10.140/media/material/study_group.vtt',
 'http://111.231.10.140/media/material/study_group_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/2.png',
 'http://111.231.10.140/media/flags/flags/us.png',
 NULL, NULL,
 'The video explains how to find group members, assign roles, and set clear weekly learning goals.',
 2),

-- u3
('u3', 'understand', 'Freshman Welcome Week Tips', 'Campus', 'Easy', '0-1min', 'OpenLearn', 'Australia', NULL, NULL,
 'http://111.231.10.140/media/material/welcome_week_tips.mp4',
 'http://111.231.10.140/media/material/welcome_week_tips_transcript.txt',
 'http://111.231.10.140/media/material/welcome_week_tips.vtt',
 'http://111.231.10.140/media/material/welcome_week_tips_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/3.png',
 'http://111.231.10.140/media/flags/flags/au.png',
 NULL, NULL,
 'The speaker shares quick tips on orientation events, map reading, and student help desks.',
 3),

-- u4
('u4', 'understand', 'How Office Hours Work', 'Academic', 'Medium', '1-2min', 'ELLLO', 'Canada', NULL, NULL,
 'http://111.231.10.140/media/material/office_hours.mp4',
 'http://111.231.10.140/media/material/office_hours_transcript.txt',
 'http://111.231.10.140/media/material/office_hours.vtt',
 'http://111.231.10.140/media/material/office_hours_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/4.png',
 'http://111.231.10.140/media/flags/flags/ca.png',
 NULL, NULL,
 'Students are encouraged to prepare questions and use office hours for assignment feedback.',
 4),

-- u5
('u5', 'understand', 'Lab Safety Briefing', 'Academic', 'Hard', '2-3min', 'OpenLearn', 'UK', NULL, NULL,
 'http://111.231.10.140/media/material/lab_safety.mp4',
 'http://111.231.10.140/media/material/lab_safety_transcript.txt',
 'http://111.231.10.140/media/material/lab_safety.vtt',
 'http://111.231.10.140/media/material/lab_safety_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/5.png',
 'http://111.231.10.140/media/flags/flags/gb.png',
 NULL, NULL,
 'The video outlines safety signs, required equipment, and incident reporting procedures.',
 5),

-- u6
('u6', 'understand', 'Finding Part-time Jobs on Campus', 'Campus', 'Easy', '1-2min', 'ELLLO', 'US', NULL, NULL,
 'http://111.231.10.140/media/material/part_time_jobs.mp4',
 'http://111.231.10.140/media/material/part_time_jobs_transcript.txt',
 'http://111.231.10.140/media/material/part_time_jobs.vtt',
 'http://111.231.10.140/media/material/part_time_jobs_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/6.png',
 'http://111.231.10.140/media/flags/flags/us.png',
 NULL, NULL,
 'It introduces job boards, resume clinics, and scheduling around class commitments.',
 6),

-- u7
('u7', 'understand', 'Referencing and Plagiarism Basics', 'Academic', 'Hard', '2-3min', 'OpenLearn', 'New Zealand', NULL, NULL,
 'http://111.231.10.140/media/material/referencing_basics.mp4',
 'http://111.231.10.140/media/material/referencing_basics_transcript.txt',
 'http://111.231.10.140/media/material/referencing_basics.vtt',
 'http://111.231.10.140/media/material/referencing_basics_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/7.png',
 'http://111.231.10.140/media/flags/flags/nz.png',
 NULL, NULL,
 'The presenter compares citation styles and explains how to avoid accidental plagiarism.',
 7),

-- u8
('u8', 'understand', 'Using the Student Health Center', 'Campus', 'Easy', '0-1min', 'OpenLearn', 'Canada', NULL, NULL,
 'http://111.231.10.140/media/material/health_center.mp4',
 'http://111.231.10.140/media/material/health_center_transcript.txt',
 'http://111.231.10.140/media/material/health_center.vtt',
 'http://111.231.10.140/media/material/health_center_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/8.png',
 'http://111.231.10.140/media/flags/flags/ca.png',
 NULL, NULL,
 'The clip covers booking methods, emergency lines, and available counseling services.',
 8),

-- u9
('u9', 'understand', 'Note-taking During Fast Lectures', 'Academic', 'Medium', '1-2min', 'ELLLO', 'US', NULL, NULL,
 'http://111.231.10.140/media/material/note_taking_fast_lectures.mp4',
 'http://111.231.10.140/media/material/note_taking_fast_lectures_transcript.txt',
 'http://111.231.10.140/media/material/note_taking_fast_lectures.vtt',
 'http://111.231.10.140/media/material/note_taking_fast_lectures_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/9.png',
 'http://111.231.10.140/media/flags/flags/us.png',
 NULL, NULL,
 'The speaker demonstrates shorthand strategies and post-class note organization.',
 9),

-- u10
('u10', 'understand', 'Managing Group Project Conflict', 'Study Skills', 'Hard', '2-3min', 'ELLLO', 'UK', NULL, NULL,
 'http://111.231.10.140/media/material/group_project_conflict.mp4',
 'http://111.231.10.140/media/material/group_project_conflict_transcript.txt',
 'http://111.231.10.140/media/material/group_project_conflict.vtt',
 'http://111.231.10.140/media/material/group_project_conflict_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/10.png',
 'http://111.231.10.140/media/flags/flags/gb.png',
 NULL, NULL,
 'The video shows how teams can clarify roles, timelines, and communication rules.',
 10),

-- u11
('u11', 'understand', 'Public Transport for New Students', 'Campus', 'Easy', '1-2min', 'OpenLearn', 'Australia', NULL, NULL,
 'http://111.231.10.140/media/material/public_transport.mp4',
 'http://111.231.10.140/media/material/public_transport_transcript.txt',
 'http://111.231.10.140/media/material/public_transport.vtt',
 'http://111.231.10.140/media/material/public_transport_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/11.png',
 'http://111.231.10.140/media/flags/flags/au.png',
 NULL, NULL,
 'It explains ticket apps, route planning, and common travel mistakes to avoid.',
 11),

-- u12
('u12', 'understand', 'Reading Academic Articles Efficiently', 'Academic', 'Medium', '2-3min', 'ELLLO', 'Ireland', NULL, NULL,
 'http://111.231.10.140/media/material/reading_articles.mp4',
 'http://111.231.10.140/media/material/reading_articles_transcript.txt',
 'http://111.231.10.140/media/material/reading_articles.vtt',
 'http://111.231.10.140/media/material/reading_articles_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover/12.png',
 'http://111.231.10.140/media/flags/flags/ie.png',
 NULL, NULL,
 'The clip presents a practical workflow for skimming abstracts and annotating key evidence.',
 12);

-- Listening and Respond 模式视频 (s1-s12)
INSERT INTO video_resources (
    video_id, mode, title, video_type, difficulty, duration, source, country, author, time_specific,
    video_url, transcript_url, vtt_url, labels_url, sample_notes_url, cover_url, flag_url,
    transcript_text, question, answer_text, sort_order
) VALUES 
-- s1
('s1', 'respond', 'Would you rather write a paper or take a test?', 'Campus', 'Medium', '0-1min', 'ELLLO', 'Thailand', 'On', '00:37',
 'http://111.231.10.140/media/material/2.mp4',
 'http://111.231.10.140/media/material/2_transcript.txt',
 'http://111.231.10.140/media/material/2.vtt',
 'http://111.231.10.140/media/material/2_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/13.png',
 'http://111.231.10.140/media/flags/flags/th.png',
 'Hello, my name is On. I\'m from Thailand. My question is would I rather write a paper or take a test?\n\nMy answer is: I would rather write a paper just because I do not like the stress that I get in the short period of time when I take a test. Moreover, if I write a paper I have more time to do research, what I do not understand, and I can manage my own schedule of when I want to do it. Therefore, that I can finish to the deadline.',
 'Explain your choice and give one reason.',
 'I would choose writing a paper because it gives me more time to organize ideas and provide stronger evidence.',
 13),

-- s2
('s2', 'respond', 'Class Presentation Q&A', 'Campus', 'Easy', '0-1min', 'ELLLO', 'UK', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/14.png',
 'http://111.231.10.140/media/flags/flags/gb.png',
 NULL,
 'How would you respond if a classmate challenges your argument?',
 'I would thank them for the question, restate my key evidence, and admit limits while suggesting further research.',
 14),

-- s3
('s3', 'respond', 'Choosing a Club This Semester', 'Campus', 'Easy', '0-1min', 'ELLLO', 'Canada', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/15.png',
 'http://111.231.10.140/media/flags/flags/ca.png',
 NULL,
 'Which club would you join first and why?',
 'I would join a debate club to improve critical thinking and speaking confidence.',
 15),

-- s4
('s4', 'respond', 'Dorm Room Study Habits', 'Campus', 'Medium', '0-1min', 'OpenLearn', 'Australia', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/16.png',
 'http://111.231.10.140/media/flags/flags/au.png',
 NULL,
 'What habit helps you study better in a shared room?',
 'I usually set a fixed quiet hour and wear headphones to stay focused.',
 16),

-- s5
('s5', 'respond', 'Managing Exam Stress', 'Academic', 'Medium', '1-2min', 'ELLLO', 'US', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/17.png',
 'http://111.231.10.140/media/flags/flags/us.png',
 NULL,
 'What would you do one week before finals to reduce stress?',
 'I would make a realistic review plan and prioritize sleep to stay efficient.',
 17),

-- s6
('s6', 'respond', 'Group Project Communication', 'Academic', 'Medium', '1-2min', 'OpenLearn', 'UK', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/18.png',
 'http://111.231.10.140/media/flags/flags/gb.png',
 NULL,
 'How do you keep a group project on track?',
 'I set clear weekly goals and use short check-ins to update progress.',
 18),

-- s7
('s7', 'respond', 'Part-time Work Balance', 'Campus', 'Easy', '0-1min', 'ELLLO', 'Ireland', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/19.png',
 'http://111.231.10.140/media/flags/flags/ie.png',
 NULL,
 'How can students balance work and study time?',
 'I would limit shifts on weekdays and protect key study blocks.',
 19),

-- s8
('s8', 'respond', 'Asking for Professor Feedback', 'Academic', 'Medium', '1-2min', 'ELLLO', 'New Zealand', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/20.png',
 'http://111.231.10.140/media/flags/flags/nz.png',
 NULL,
 'What would you say when requesting assignment feedback?',
 'I would ask specific questions about weak sections and how to improve them.',
 20),

-- s9
('s9', 'respond', 'Adapting to a New City', 'Campus', 'Easy', '0-1min', 'OpenLearn', 'Germany', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/21.png',
 'http://111.231.10.140/media/flags/flags/de.png',
 NULL,
 'What is one way to adapt quickly to a new city?',
 'I would learn transportation routes first because it makes daily life easier.',
 21),

-- s10
('s10', 'respond', 'Improving Listening Skills', 'Academic', 'Hard', '1-2min', 'ELLLO', 'Japan', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/22.png',
 'http://111.231.10.140/media/flags/flags/jp.png',
 NULL,
 'Which listening strategy works best for you?',
 'I replay short clips and note keywords before checking the transcript.',
 22),

-- s11
('s11', 'respond', 'Leading a Seminar Discussion', 'Academic', 'Hard', '1-2min', 'OpenLearn', 'Singapore', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/23.png',
 'http://111.231.10.140/media/flags/flags/sg.png',
 NULL,
 'How would you start a seminar discussion confidently?',
 'I would begin with one clear question and invite two viewpoints first.',
 23),

-- s12
('s12', 'respond', 'Preparing a Short Speech', 'Campus', 'Medium', '1-2min', 'ELLLO', 'France', NULL, NULL,
 'http://111.231.10.140/media/material/presentation_qa.mp4',
 'http://111.231.10.140/media/material/presentation_qa_transcript.txt',
 'http://111.231.10.140/media/material/presentation_qa.vtt',
 'http://111.231.10.140/media/material/presentation_qa_labels.txt',
 NULL,
 'http://111.231.10.140/media/cover2/24.png',
 'http://111.231.10.140/media/flags/flags/fr.png',
 NULL,
 'How do you prepare a one-minute speech quickly?',
 'I focus on one message, three points, and one practical example.',
 24);
