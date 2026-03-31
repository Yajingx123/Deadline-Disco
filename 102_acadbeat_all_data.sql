-- Academic English Practice Platform Seed Data
-- Run this AFTER 101_acadbeat_all_tables.sql
SET SQL_SAFE_UPDATES = 0;
USE acadbeat;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM vocab_session_responses;
DELETE FROM vocab_session_items;
DELETE FROM vocab_sessions;
DELETE FROM vocab_user_word_progress;
DELETE FROM vocab_user_wordbook_selections;
DELETE FROM vocab_word_book_words;
DELETE FROM vocab_words;
DELETE FROM vocab_word_books;

DELETE FROM training_responses;
DELETE FROM training_attempts;
DELETE FROM training_item_configs;
DELETE FROM training_items;
DELETE FROM training_modules;

DELETE FROM forum_post_favorites;
DELETE FROM forum_post_likes;
DELETE FROM challenge_team_invites;
DELETE FROM challenge_team_members;
DELETE FROM challenge_teams;
DELETE FROM challenge_meta;
DELETE FROM challenge_signups;
DELETE FROM challenge_team_public_listings;
DELETE FROM message_center_notice_reads;
DELETE FROM message_center_notifications;
DELETE FROM message_center_system_notices;
DELETE FROM forum_post_labels;
DELETE FROM forum_labels;
DELETE FROM forum_comment_media;
DELETE FROM forum_comments;
DELETE FROM forum_post_media;
DELETE FROM forum_posts;
DELETE FROM chat_message_media;
DELETE FROM chat_messages;
DELETE FROM chat_conversation_members;
DELETE FROM chat_conversations;

DELETE FROM checkin_records;
DELETE FROM checkin_partnerships;
DELETE FROM users;

ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE vocab_word_books AUTO_INCREMENT = 1;
ALTER TABLE vocab_words AUTO_INCREMENT = 1;
ALTER TABLE vocab_user_wordbook_selections AUTO_INCREMENT = 1;
ALTER TABLE vocab_sessions AUTO_INCREMENT = 1;
ALTER TABLE vocab_session_items AUTO_INCREMENT = 1;
ALTER TABLE vocab_session_responses AUTO_INCREMENT = 1;
ALTER TABLE forum_post_likes AUTO_INCREMENT = 1;
ALTER TABLE forum_post_favorites AUTO_INCREMENT = 1;
ALTER TABLE challenge_teams AUTO_INCREMENT = 1;
ALTER TABLE challenge_team_members AUTO_INCREMENT = 1;
ALTER TABLE challenge_team_invites AUTO_INCREMENT = 1;
ALTER TABLE challenge_signups AUTO_INCREMENT = 1;
ALTER TABLE challenge_team_public_listings AUTO_INCREMENT = 1;
ALTER TABLE message_center_notice_reads AUTO_INCREMENT = 1;
ALTER TABLE message_center_notifications AUTO_INCREMENT = 1;
ALTER TABLE message_center_system_notices AUTO_INCREMENT = 1;
ALTER TABLE chat_conversations AUTO_INCREMENT = 1;
ALTER TABLE chat_conversation_members AUTO_INCREMENT = 1;
ALTER TABLE chat_messages AUTO_INCREMENT = 1;
ALTER TABLE chat_message_media AUTO_INCREMENT = 1;

INSERT INTO forum_labels (label_id, name, created_at) VALUES
(1, 'Current news', '2026-03-23 09:00:00'),
(2, 'Seek help', '2026-03-23 09:00:00'),
(3, 'Viewpoint topic', '2026-03-23 09:00:00'),
(4, 'Study tips', '2026-03-23 09:00:00'),
(5, 'Course guide', '2026-03-23 09:00:00'),
(6, 'Campus life', '2026-03-23 09:00:00');

INSERT INTO forum_posts (post_id, user_id, title, content_text, view_count, comment_count, last_commented_at, status, created_at, updated_at) VALUES
(1, 1, 'How should we balance AI tools and original writing in class?', 'Our seminar keeps debating where AI support becomes too much. I am curious how other students define a fair boundary between drafting support and actual authorship.\n\n**What counts as acceptable help** in your course right now?', 184, 2, '2026-03-23 12:40:00', 'active', '2026-03-22 19:10:00', '2026-03-23 12:40:00'),
(2, 2, 'Need help choosing between database systems and web development electives', 'I can only keep one elective this term. The database course looks practical, but the web development studio might help my portfolio faster.\n\nIf you took either one, what was the workload really like?', 96, 1, '2026-03-23 11:20:00', 'active', '2026-03-22 21:00:00', '2026-03-23 11:20:00'),
(3, 1, 'Current campus wifi slowdown near the library?', 'Has anyone else noticed the campus wifi dropping near the library this week? It gets unstable right when I upload project files, and I am trying to figure out whether it is my laptop or the network itself.', 58, 0, NULL, 'active', '2026-03-23 08:15:00', '2026-03-23 08:15:00'),
(4, 3, 'What makes a forum thread genuinely useful to future students?', 'I think the best threads are not the loudest ones. The useful ones usually include context, final outcomes, and the exact steps that solved the problem.\n\nWhat should every high-quality help thread include?', 121, 1, '2026-03-23 13:05:00', 'active', '2026-03-23 09:30:00', '2026-03-23 13:05:00');

INSERT INTO forum_post_labels (post_label_id, post_id, label_id) VALUES
(1, 1, 1),
(2, 1, 3),
(3, 2, 2),
(4, 2, 5),
(5, 3, 1),
(6, 3, 6),
(7, 4, 2),
(8, 4, 3);

INSERT INTO forum_comments (comment_id, post_id, user_id, parent_comment_id, content_text, status, created_at, updated_at) VALUES
(1, 1, 2, NULL, 'In my writing class, outlining ideas with AI is allowed, but paragraph-level drafting is not. The teacher said the key test is whether you can still explain and defend every sentence yourself.', 'active', '2026-03-23 10:05:00', '2026-03-23 10:05:00'),
(2, 1, 1, 1, 'That explanation actually sounds reasonable. The line is still blurry, but the “can you defend every sentence” test is stronger than just banning tools entirely.', 'active', '2026-03-23 12:40:00', '2026-03-23 12:40:00'),
(3, 2, 1, NULL, 'I took the database systems course last term. The workload was steady rather than explosive, and the SQL practice paid off immediately in projects. If you want practical backend value, I would lean database first.', 'active', '2026-03-23 11:20:00', '2026-03-23 11:20:00'),
(4, 4, 2, NULL, 'A useful thread should always include the final fix, not just the original question. Otherwise future students read five paragraphs of confusion and still leave empty-handed.', 'active', '2026-03-23 13:05:00', '2026-03-23 13:05:00');

INSERT INTO users (user_id, username, email, password_hash, avatar_url, role) VALUES
(1, 'demo_student', 'demo@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(2, 'noura', 'noura@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(3, 'emily', 'emily@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(4, 'samira', 'samira@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(5, 'benjamin', 'benjamin@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(6, 'karen', 'karen@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(7, 'shu', 'shu@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL, 'user'),
(8, 'admin', 'admin@acadbeat.local', '$2y$12$XhbLl3R3gxfC/TD8.gECzugA8voSjXrHp11yHZXoH7Pm74Bno7boi', NULL, 'admin');

INSERT INTO chat_conversations (conversation_id, conversation_type, title, created_by_user_id, last_message_at, status, created_at, updated_at) VALUES
(1, 'direct', NULL, 1, '2026-03-24 09:18:00', 'active', '2026-03-24 09:00:00', '2026-03-24 09:18:00'),
(2, 'direct', NULL, 1, '2026-03-24 12:42:00', 'active', '2026-03-24 12:00:00', '2026-03-24 12:42:00'),
(3, 'group', 'Research Sprint', 1, '2026-03-25 16:25:00', 'active', '2026-03-25 15:30:00', '2026-03-25 16:25:00');

INSERT INTO chat_conversation_members (conversation_member_id, conversation_id, user_id, member_role, last_read_at, joined_at, created_at, updated_at) VALUES
(1, 1, 1, 'owner', '2026-03-24 09:18:00', '2026-03-24 09:00:00', '2026-03-24 09:00:00', '2026-03-24 09:18:00'),
(2, 1, 2, 'member', '2026-03-24 09:18:00', '2026-03-24 09:00:00', '2026-03-24 09:00:00', '2026-03-24 09:18:00'),
(3, 2, 1, 'owner', '2026-03-24 12:42:00', '2026-03-24 12:00:00', '2026-03-24 12:00:00', '2026-03-24 12:42:00'),
(4, 2, 3, 'member', '2026-03-24 12:42:00', '2026-03-24 12:00:00', '2026-03-24 12:00:00', '2026-03-24 12:42:00'),
(5, 3, 1, 'owner', '2026-03-25 16:25:00', '2026-03-25 15:30:00', '2026-03-25 15:30:00', '2026-03-25 16:25:00'),
(6, 3, 4, 'member', '2026-03-25 16:25:00', '2026-03-25 15:32:00', '2026-03-25 15:32:00', '2026-03-25 16:25:00'),
(7, 3, 5, 'member', '2026-03-25 16:25:00', '2026-03-25 15:33:00', '2026-03-25 15:33:00', '2026-03-25 16:25:00');

INSERT INTO chat_messages (message_id, conversation_id, user_id, content_text, status, created_at, updated_at) VALUES
(1, 1, 2, 'Hi! I found a useful article about discourse markers for tomorrow''s seminar. [Open the resource](https://owl.purdue.edu/owl/general_writing/academic_writing/index.html)', 'active', '2026-03-24 09:03:00', '2026-03-24 09:03:00'),
(2, 1, 1, 'Perfect. I will review it after class and send my notes tonight. 😊', 'active', '2026-03-24 09:18:00', '2026-03-24 09:18:00'),
(3, 2, 3, 'Can you listen to my speaking draft later? I just uploaded the clip. ![audio:sample-feedback.webm](http://127.0.0.1:8001/forum-project/uploads/audio/recording-1774277487228-1223e8bf9466.webm)', 'active', '2026-03-24 12:14:00', '2026-03-24 12:14:00'),
(4, 2, 1, 'Yes. The opening is already strong. I also attached the slide screenshot I mentioned. ![presentation-reference](http://127.0.0.1:8001/forum-project/uploads/image/Screenshot-2026-03-24-004358-505636a13442.png)', 'active', '2026-03-24 12:42:00', '2026-03-24 12:42:00'),
(5, 3, 1, 'Let''s use this group for the research sprint. Drop sources, drafts, or voice notes here.', 'active', '2026-03-25 15:35:00', '2026-03-25 15:35:00'),
(6, 3, 4, 'I added the library report link here: [Campus library usage](https://example.com/library-report)', 'active', '2026-03-25 15:48:00', '2026-03-25 15:48:00'),
(7, 3, 5, 'I will upload the interview audio after class.', 'active', '2026-03-25 16:25:00', '2026-03-25 16:25:00');

INSERT INTO chat_message_media (chat_message_media_id, message_id, media_type, media_url, order_index, created_at) VALUES
(1, 1, 'link', 'https://owl.purdue.edu/owl/general_writing/academic_writing/index.html', 1, '2026-03-24 09:03:00'),
(2, 3, 'audio', 'http://127.0.0.1:8001/forum-project/uploads/audio/recording-1774277487228-1223e8bf9466.webm', 1, '2026-03-24 12:14:00'),
(3, 4, 'image', 'http://127.0.0.1:8001/forum-project/uploads/image/Screenshot-2026-03-24-004358-505636a13442.png', 1, '2026-03-24 12:42:00'),
(4, 6, 'link', 'https://example.com/library-report', 1, '2026-03-25 15:48:00');

INSERT INTO message_center_notifications (notification_id, recipient_user_id, actor_user_id, notification_type, post_id, comment_id, title, body_text, cta_label, cta_url, is_read, created_at, updated_at) VALUES
(1, 1, 2, 'reply', 1, 1, 'benjamin replied to your post', 'In my writing class, outlining ideas with AI is allowed, but paragraph-level drafting is not.', 'Reply', 'http://127.0.0.1:5173/?view=forum&postId=1', 0, '2026-03-23 10:05:00', '2026-03-23 10:05:00'),
(2, 1, 3, 'like', 1, NULL, 'demo_student liked your post', 'How should we balance AI tools and original writing in class?', 'View post', 'http://127.0.0.1:5173/?view=forum&postId=1', 0, '2026-03-23 13:10:00', '2026-03-23 13:10:00'),
(3, 2, 1, 'favorite', 2, NULL, 'emily favorited your post', 'Need help choosing between database systems and web development electives', 'View post', 'http://127.0.0.1:5173/?view=forum&postId=2', 1, '2026-03-23 14:24:00', '2026-03-23 14:24:00');

INSERT INTO message_center_system_notices (notice_id, title, body_text, cta_label, cta_url, status, created_at, updated_at) VALUES
(1, 'Community review standards refreshed', 'Posts with clearer titles and final outcomes are now surfaced more prominently in the forum. Edit older threads if you want them to remain discoverable.', 'Open forum', 'http://127.0.0.1:5173/?view=forum', 'active', '2026-03-22 09:00:00', '2026-03-22 09:00:00'),
(2, 'Media uploads now support audio and screenshots', 'You can attach screenshots, links, and audio clips inside personal messages and forum replies. Keep files relevant to the thread topic.', 'Open message center', 'http://127.0.0.1:5173/?view=messages', 'active', '2026-03-24 08:30:00', '2026-03-24 08:30:00');

INSERT INTO vocab_word_books (word_book_id, slug, title, description) VALUES
(1, 'daily', 'Daily life & campus', '15 high-frequency words for lectures, assignments, study routines, and campus life.'),
(2, 'cs', 'CS core vocabulary', '15 starter CS words covering algorithms, code structure, debugging, data, and systems.'),
(3, 'mech', 'Mechanical engineering', '15 foundation words for motion, machines, materials, and manufacturing workflows.'),
(4, 'civil', 'Civil engineering', '15 construction and structure terms for materials, site work, and load transfer.'),
(5, 'traffic', 'Traffic & transport', '15 core terms for road systems, transit planning, traffic flow, and routing.'),
(6, 'math', 'Math foundations', '15 key words for algebra, calculus, geometry, and proof-based study.');

INSERT INTO vocab_words (word_id, word, phonetic, meaning_en, meaning_zh, sentence, image_url, audio_url) VALUES
(1, 'syllabus', '/ˈsɪləbəs/', 'n. a document that outlines a course, its topics, and requirements', '课程大纲；教学安排', 'The professor uploaded the syllabus before the first class.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=syllabus', 'https://dict.youdao.com/dictvoice?audio=syllabus&type=2'),
(2, 'lecture', '/ˈlektʃə(r)/', 'n. a formal talk given to students, especially at a university', '讲座；课堂授课', 'Today''s lecture focused on climate policy.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=lecture', 'https://dict.youdao.com/dictvoice?audio=lecture&type=2'),
(3, 'seminar', '/ˈsemɪnɑː(r)/', 'n. a small class in which students discuss a subject with a teacher', '研讨课；讨论班', 'We discussed the article in yesterday''s seminar.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=seminar', 'https://dict.youdao.com/dictvoice?audio=seminar&type=2'),
(4, 'assignment', '/əˈsaɪnmənt/', 'n. a task or piece of work given to students', '作业；任务', 'I need to finish my assignment by Friday.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=assignment', 'https://dict.youdao.com/dictvoice?audio=assignment&type=2'),
(5, 'deadline', '/ˈdedlaɪn/', 'n. the latest time by which something must be completed', '截止日期', 'The project deadline is next Monday.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=deadline', 'https://dict.youdao.com/dictvoice?audio=deadline&type=2'),
(6, 'tutorial', '/tjuːˈtɔːriəl/', 'n. a small teaching session that gives students extra guidance', '辅导课；教程课', 'Our tutorial helped me understand the formula.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=tutorial', 'https://dict.youdao.com/dictvoice?audio=tutorial&type=2'),
(7, 'revision', '/rɪˈvɪʒn/', 'n. the act of studying again in order to prepare for an exam', '复习', 'She made a revision plan for the final week.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=revision', 'https://dict.youdao.com/dictvoice?audio=revision&type=2'),
(8, 'attendance', '/əˈtendəns/', 'n. the fact of being present at school, class, or work', '出勤；到课情况', 'Attendance is required for all lab sessions.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=attendance', 'https://dict.youdao.com/dictvoice?audio=attendance&type=2'),
(9, 'cafeteria', '/ˌkæfəˈtɪəriə/', 'n. a place where students or workers can buy meals', '自助食堂；学生餐厅', 'We met in the cafeteria after class.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=cafeteria', 'https://dict.youdao.com/dictvoice?audio=cafeteria&type=2'),
(10, 'library', '/ˈlaɪbrəri/', 'n. a place where books and other resources are kept for reading or borrowing', '图书馆', 'The library stays open until midnight during exams.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=library', 'https://dict.youdao.com/dictvoice?audio=library&type=2'),
(11, 'roommate', '/ˈruːmmeɪt/', 'n. a person who shares a room or apartment with another person', '室友', 'My roommate helps me practise English every evening.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=roommate', 'https://dict.youdao.com/dictvoice?audio=roommate&type=2'),
(12, 'timetable', '/ˈtaɪmteɪbl/', 'n. a schedule showing when classes or events happen', '时间表；课程表', 'Check your timetable before choosing new modules.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=timetable', 'https://dict.youdao.com/dictvoice?audio=timetable&type=2'),
(13, 'presentation', '/ˌpreznˈteɪʃn/', 'n. a talk in which information is given to an audience', '展示；演讲', 'Each group must give a short presentation next week.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=presentation', 'https://dict.youdao.com/dictvoice?audio=presentation&type=2'),
(14, 'notebook', '/ˈnəʊtbʊk/', 'n. a book of blank pages for writing notes', '笔记本', 'I wrote the key points in my notebook.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=notebook', 'https://dict.youdao.com/dictvoice?audio=notebook&type=2'),
(15, 'laboratory', '/ləˈbɒrətri/', 'n. a room or building used for scientific experiments or research', '实验室', 'The chemistry laboratory requires safety glasses.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=laboratory', 'https://dict.youdao.com/dictvoice?audio=laboratory&type=2'),
(16, 'algorithm', '/ˈælɡərɪðəm/', 'n. a step-by-step method for solving a problem or performing a computation', '算法', 'The algorithm sorts the data in linear time.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=algorithm', 'https://dict.youdao.com/dictvoice?audio=algorithm&type=2'),
(17, 'variable', '/ˈveəriəbl/', 'n. a named value in a program that can change', '变量', 'Store the user''s score in a variable.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=variable', 'https://dict.youdao.com/dictvoice?audio=variable&type=2'),
(18, 'function', '/ˈfʌŋkʃn/', 'n. a block of code designed to perform a specific task', '函数；功能块', 'We wrote a function to validate the input.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=function', 'https://dict.youdao.com/dictvoice?audio=function&type=2'),
(19, 'compiler', '/kəmˈpaɪlə(r)/', 'n. a program that translates source code into machine code', '编译器', 'The compiler reported a missing semicolon.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=compiler', 'https://dict.youdao.com/dictvoice?audio=compiler&type=2'),
(20, 'database', '/ˈdeɪtəbeɪs/', 'n. an organized collection of data stored on a computer system', '数据库', 'The database stores user profiles and scores.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=database', 'https://dict.youdao.com/dictvoice?audio=database&type=2'),
(21, 'network', '/ˈnetwɜːk/', 'n. a group of connected computers or devices', '网络', 'The network was unavailable during the test.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=network', 'https://dict.youdao.com/dictvoice?audio=network&type=2'),
(22, 'interface', '/ˈɪntəfeɪs/', 'n. the point where a user interacts with a computer system', '界面；接口', 'The new interface is cleaner and easier to use.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=interface', 'https://dict.youdao.com/dictvoice?audio=interface&type=2'),
(23, 'recursion', '/rɪˈkɜːʃən/', 'n. a method in which a function calls itself to solve a problem', '递归', 'Recursion is useful for tree traversal problems.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=recursion', 'https://dict.youdao.com/dictvoice?audio=recursion&type=2'),
(24, 'debugging', '/ˌdiːˈbʌɡɪŋ/', 'n. the process of finding and fixing errors in software', '调试；排错', 'Debugging took longer than writing the code.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=debugging', 'https://dict.youdao.com/dictvoice?audio=debugging&type=2'),
(25, 'syntax', '/ˈsɪntæks/', 'n. the rules that define how code must be written', '语法', 'A syntax error stopped the script from running.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=syntax', 'https://dict.youdao.com/dictvoice?audio=syntax&type=2'),
(26, 'framework', '/ˈfreɪmwɜːk/', 'n. a reusable structure that helps developers build software', '框架', 'React is a framework for building user interfaces.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=framework', 'https://dict.youdao.com/dictvoice?audio=framework&type=2'),
(27, 'repository', '/rɪˈpɒzətri/', 'n. a storage location for code, documents, or other project files', '代码仓库；存储库', 'Push your changes to the repository before Friday.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=repository', 'https://dict.youdao.com/dictvoice?audio=repository&type=2'),
(28, 'encryption', '/ɪnˈkrɪpʃən/', 'n. the process of converting information into a secure coded form', '加密', 'Encryption protects the data during transmission.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=encryption', 'https://dict.youdao.com/dictvoice?audio=encryption&type=2'),
(29, 'array', '/əˈreɪ/', 'n. a data structure that stores a collection of items', '数组', 'The array contains ten integer values.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=array', 'https://dict.youdao.com/dictvoice?audio=array&type=2'),
(30, 'backend', '/ˈbækend/', 'n. the server-side part of an application that handles logic and data', '后端；服务端逻辑层', 'The backend validates the request before saving it.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=backend', 'https://dict.youdao.com/dictvoice?audio=backend&type=2'),
(31, 'torque', '/tɔːk/', 'n. a turning force that causes rotation around an axis', '扭矩', 'Increasing torque helped the motor lift the load.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=torque', 'https://dict.youdao.com/dictvoice?audio=torque&type=2'),
(32, 'friction', '/ˈfrɪkʃn/', 'n. the force that resists motion between two surfaces', '摩擦力', 'Too much friction reduced the machine''s efficiency.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=friction', 'https://dict.youdao.com/dictvoice?audio=friction&type=2'),
(33, 'piston', '/ˈpɪstən/', 'n. a moving part in an engine or pump that transfers force', '活塞', 'The piston moves up and down inside the cylinder.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=piston', 'https://dict.youdao.com/dictvoice?audio=piston&type=2'),
(34, 'turbine', '/ˈtɜːbaɪn/', 'n. a machine with blades that spins when fluid passes through it', '涡轮机', 'The turbine generated power from the steam flow.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=turbine', 'https://dict.youdao.com/dictvoice?audio=turbine&type=2'),
(35, 'bearing', '/ˈbeərɪŋ/', 'n. a part that supports moving machine components and reduces friction', '轴承', 'A worn bearing caused the shaft to vibrate.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=bearing', 'https://dict.youdao.com/dictvoice?audio=bearing&type=2'),
(36, 'gearbox', '/ˈɡɪəbɒks/', 'n. a set of gears that transmits power at different speeds', '变速箱；齿轮箱', 'The gearbox changed the output speed of the motor.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=gearbox', 'https://dict.youdao.com/dictvoice?audio=gearbox&type=2'),
(37, 'prototype', '/ˈprəʊtətaɪp/', 'n. an early model used to test a design', '原型；样机', 'The team built a prototype before mass production.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=prototype', 'https://dict.youdao.com/dictvoice?audio=prototype&type=2'),
(38, 'blueprint', '/ˈbluːprɪnt/', 'n. a detailed technical drawing or plan for building something', '蓝图；设计图', 'The engineer checked the blueprint before assembly.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=blueprint', 'https://dict.youdao.com/dictvoice?audio=blueprint&type=2'),
(39, 'welding', '/ˈweldɪŋ/', 'n. the process of joining metal parts by heating them', '焊接', 'Proper welding is essential for a strong frame.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=welding', 'https://dict.youdao.com/dictvoice?audio=welding&type=2'),
(40, 'tensile', '/ˈtensaɪl/', 'adj. related to being stretched or pulled', '拉伸的；抗拉的', 'The material showed high tensile strength in the test.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=tensile', 'https://dict.youdao.com/dictvoice?audio=tensile&type=2'),
(41, 'nozzle', '/ˈnɒzl/', 'n. a device that controls the direction or speed of fluid flow', '喷嘴', 'The nozzle directed the fuel into the chamber.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=nozzle', 'https://dict.youdao.com/dictvoice?audio=nozzle&type=2'),
(42, 'shaft', '/ʃɑːft/', 'n. a long rotating rod that transmits mechanical power', '轴', 'The shaft connects the motor to the gear system.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=shaft', 'https://dict.youdao.com/dictvoice?audio=shaft&type=2'),
(43, 'lubricant', '/ˈluːbrɪkənt/', 'n. a substance used to reduce friction between surfaces', '润滑剂', 'The technician applied lubricant to the moving joints.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=lubricant', 'https://dict.youdao.com/dictvoice?audio=lubricant&type=2'),
(44, 'assembly', '/əˈsembli/', 'n. the process of putting parts together to form a machine or structure', '装配；组装', 'Final assembly began after every part passed inspection.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=assembly', 'https://dict.youdao.com/dictvoice?audio=assembly&type=2'),
(45, 'calibration', '/ˌkælɪˈbreɪʃn/', 'n. the process of adjusting an instrument for accuracy', '校准', 'Regular calibration keeps the sensor readings accurate.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=calibration', 'https://dict.youdao.com/dictvoice?audio=calibration&type=2'),
(46, 'concrete', '/ˈkɒŋkriːt/', 'n. a hard building material made from cement, sand, and stone', '混凝土', 'Concrete was poured into the bridge foundation.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=concrete', 'https://dict.youdao.com/dictvoice?audio=concrete&type=2'),
(47, 'beam', '/biːm/', 'n. a horizontal structural element that supports loads', '梁', 'The steel beam carried the roof load.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=beam', 'https://dict.youdao.com/dictvoice?audio=beam&type=2'),
(48, 'column', '/ˈkɒləm/', 'n. a vertical structure that supports weight above it', '柱', 'Each column transfers load to the foundation.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=column', 'https://dict.youdao.com/dictvoice?audio=column&type=2'),
(49, 'foundation', '/faʊnˈdeɪʃn/', 'n. the lowest part of a structure that supports it from below', '地基；基础', 'The foundation must be stable before construction begins.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=foundation', 'https://dict.youdao.com/dictvoice?audio=foundation&type=2'),
(50, 'scaffold', '/ˈskæfəʊld/', 'n. a temporary platform used by workers during construction', '脚手架', 'Workers climbed the scaffold to reach the upper wall.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=scaffold', 'https://dict.youdao.com/dictvoice?audio=scaffold&type=2'),
(51, 'drainage', '/ˈdreɪnɪdʒ/', 'n. the system used to remove water from an area', '排水系统', 'Good drainage prevents water from damaging the road.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=drainage', 'https://dict.youdao.com/dictvoice?audio=drainage&type=2'),
(52, 'pavement', '/ˈpeɪvmənt/', 'n. a hard surface for roads or walkways', '路面；人行道', 'The pavement was replaced after the utility work.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=pavement', 'https://dict.youdao.com/dictvoice?audio=pavement&type=2'),
(53, 'trench', '/trentʃ/', 'n. a long narrow hole dug in the ground for pipes or cables', '沟槽；壕沟', 'The crew dug a trench for the water line.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=trench', 'https://dict.youdao.com/dictvoice?audio=trench&type=2'),
(54, 'rebar', '/ˈriːbɑː(r)/', 'n. steel bars used to strengthen concrete', '钢筋', 'Rebar was placed before the concrete was poured.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=rebar', 'https://dict.youdao.com/dictvoice?audio=rebar&type=2'),
(55, 'surveying', '/səˈveɪɪŋ/', 'n. the process of measuring land and mapping positions', '测量；勘测', 'Surveying ensured the building was aligned correctly.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=surveying', 'https://dict.youdao.com/dictvoice?audio=surveying&type=2'),
(56, 'masonry', '/ˈmeɪsnri/', 'n. construction work done with stone or brick', '砖石工程', 'Masonry walls were chosen for the historic building.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=masonry', 'https://dict.youdao.com/dictvoice?audio=masonry&type=2'),
(57, 'excavation', '/ˌekskəˈveɪʃn/', 'n. the act of removing earth to create space for construction', '开挖；挖掘', 'Excavation started after the site inspection.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=excavation', 'https://dict.youdao.com/dictvoice?audio=excavation&type=2'),
(58, 'retaining', '/rɪˈteɪnɪŋ/', 'adj. designed to hold back soil or water', '挡土的；支护的', 'The retaining wall protected the slope from collapse.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=retaining', 'https://dict.youdao.com/dictvoice?audio=retaining&type=2'),
(59, 'cement', '/sɪˈment/', 'n. a powder used to make concrete and mortar', '水泥', 'The mix needed more cement for extra strength.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=cement', 'https://dict.youdao.com/dictvoice?audio=cement&type=2'),
(60, 'loadbearing', '/ˈləʊdˌbeərɪŋ/', 'adj. supporting structural weight', '承重的', 'The loadbearing wall could not be removed in the redesign.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=loadbearing', 'https://dict.youdao.com/dictvoice?audio=loadbearing&type=2'),
(61, 'intersection', '/ˌɪntəˈsekʃn/', 'n. a place where two or more roads cross', '交叉路口', 'Traffic slowed down near the busy intersection.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=intersection', 'https://dict.youdao.com/dictvoice?audio=intersection&type=2'),
(62, 'signal', '/ˈsɪɡnəl/', 'n. a traffic light or sign that controls road movement', '信号；交通信号灯', 'The signal turned green after a short delay.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=signal', 'https://dict.youdao.com/dictvoice?audio=signal&type=2'),
(63, 'lane', '/leɪn/', 'n. a marked strip of road for a line of vehicles', '车道', 'Please stay in the left lane before turning.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=lane', 'https://dict.youdao.com/dictvoice?audio=lane&type=2'),
(64, 'congestion', '/kənˈdʒestʃən/', 'n. a condition in which traffic is crowded and moves slowly', '拥堵', 'Morning congestion was worse after the accident.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=congestion', 'https://dict.youdao.com/dictvoice?audio=congestion&type=2'),
(65, 'detour', '/ˈdiːtʊə(r)/', 'n. an alternative route used when the usual route is blocked', '绕行路线', 'Drivers followed the detour during road maintenance.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=detour', 'https://dict.youdao.com/dictvoice?audio=detour&type=2'),
(66, 'pedestrian', '/pəˈdestriən/', 'n. a person walking rather than travelling in a vehicle', '行人', 'The crossing gives pedestrians more time to pass.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=pedestrian', 'https://dict.youdao.com/dictvoice?audio=pedestrian&type=2'),
(67, 'highway', '/ˈhaɪweɪ/', 'n. a main road designed for fast travel over long distances', '高速公路', 'The highway connects the two major cities.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=highway', 'https://dict.youdao.com/dictvoice?audio=highway&type=2'),
(68, 'roundabout', '/ˈraʊndəbaʊt/', 'n. a circular intersection where traffic moves around a central island', '环岛', 'A roundabout reduced delays at the junction.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=roundabout', 'https://dict.youdao.com/dictvoice?audio=roundabout&type=2'),
(69, 'transit', '/ˈtrænzɪt/', 'n. the movement of people or goods from one place to another; public transport', '公共交通；运输', 'The city invested more in rapid transit.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=transit', 'https://dict.youdao.com/dictvoice?audio=transit&type=2'),
(70, 'corridor', '/ˈkɒrɪdɔː(r)/', 'n. a main route used for transportation between places', '交通走廊', 'The rail corridor links the port to inland cities.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=corridor', 'https://dict.youdao.com/dictvoice?audio=corridor&type=2'),
(71, 'junction', '/ˈdʒʌŋkʃn/', 'n. a point where roads or railway lines meet', '枢纽；交汇处', 'The junction handles heavy commuter traffic.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=junction', 'https://dict.youdao.com/dictvoice?audio=junction&type=2'),
(72, 'commute', '/kəˈmjuːt/', 'n. a regular trip between home and work or school', '通勤', 'Her daily commute takes nearly an hour.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=commute', 'https://dict.youdao.com/dictvoice?audio=commute&type=2'),
(73, 'throughput', '/ˈθruːpʊt/', 'n. the amount of traffic or material passing through a system in a given time', '通行能力；吞吐量', 'The redesign improved throughput at the station entrance.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=throughput', 'https://dict.youdao.com/dictvoice?audio=throughput&type=2'),
(74, 'signage', '/ˈsaɪnɪdʒ/', 'n. signs used to give directions or information', '标志系统；指示牌', 'Clear signage helped visitors find the bus terminal.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=signage', 'https://dict.youdao.com/dictvoice?audio=signage&type=2'),
(75, 'toll', '/təʊl/', 'n. a fee paid to use a road, bridge, or tunnel', '通行费', 'Drivers can pay the toll electronically now.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=toll', 'https://dict.youdao.com/dictvoice?audio=toll&type=2'),
(76, 'theorem', '/ˈθɪərəm/', 'n. a statement that has been proved using logic and mathematics', '定理', 'The theorem was introduced in the first week of class.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=theorem', 'https://dict.youdao.com/dictvoice?audio=theorem&type=2'),
(77, 'proof', '/pruːf/', 'n. a logical argument showing that a statement is true', '证明', 'She wrote a clear proof for the final step.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=proof', 'https://dict.youdao.com/dictvoice?audio=proof&type=2'),
(78, 'equation', '/ɪˈkweɪʒn/', 'n. a mathematical statement showing that two things are equal', '方程', 'The equation has two possible solutions.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=equation', 'https://dict.youdao.com/dictvoice?audio=equation&type=2'),
(79, 'derivative', '/dɪˈrɪvətɪv/', 'n. the rate at which one quantity changes in relation to another', '导数', 'The derivative measures the slope of the curve.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=derivative', 'https://dict.youdao.com/dictvoice?audio=derivative&type=2'),
(80, 'integral', '/ˈɪntɪɡrəl/', 'n. a quantity found by summing infinitely small parts', '积分', 'The integral gives the area under the graph.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=integral', 'https://dict.youdao.com/dictvoice?audio=integral&type=2'),
(81, 'matrix', '/ˈmeɪtrɪks/', 'n. a rectangular arrangement of numbers in rows and columns', '矩阵', 'The matrix represents the transformation.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=matrix', 'https://dict.youdao.com/dictvoice?audio=matrix&type=2'),
(82, 'vector', '/ˈvektə(r)/', 'n. a quantity with both size and direction', '向量', 'Each vector in the set has two components.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=vector', 'https://dict.youdao.com/dictvoice?audio=vector&type=2'),
(83, 'sequence', '/ˈsiːkwəns/', 'n. a set of numbers arranged in a particular order', '数列', 'The sequence increases by three each step.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=sequence', 'https://dict.youdao.com/dictvoice?audio=sequence&type=2'),
(84, 'limit', '/ˈlɪmɪt/', 'n. the value a function approaches as input changes', '极限', 'We calculated the limit as x approached zero.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=limit', 'https://dict.youdao.com/dictvoice?audio=limit&type=2'),
(85, 'ratio', '/ˈreɪʃiəʊ/', 'n. a relationship between two quantities showing how much of one there is compared to the other', '比率', 'The ratio of width to height remained constant.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=ratio', 'https://dict.youdao.com/dictvoice?audio=ratio&type=2'),
(86, 'polygon', '/ˈpɒliɡɒn/', 'n. a flat shape with straight sides', '多边形', 'A triangle is the simplest polygon.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=polygon', 'https://dict.youdao.com/dictvoice?audio=polygon&type=2'),
(87, 'tangent', '/ˈtændʒənt/', 'n. a line that touches a curve at one point without crossing it', '切线', 'The tangent touches the circle at exactly one point.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=tangent', 'https://dict.youdao.com/dictvoice?audio=tangent&type=2'),
(88, 'modulus', '/ˈmɒdjələs/', 'n. the absolute value of a number; in algebra, the remainder operation context', '模；绝对值', 'The modulus of negative five is five.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=modulus', 'https://dict.youdao.com/dictvoice?audio=modulus&type=2'),
(89, 'fraction', '/ˈfrækʃn/', 'n. a number that represents part of a whole', '分数', 'Convert the fraction into a decimal first.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=fraction', 'https://dict.youdao.com/dictvoice?audio=fraction&type=2'),
(90, 'symmetry', '/ˈsɪmətri/', 'n. the quality of having matching parts on opposite sides', '对称性', 'The graph shows symmetry around the y-axis.', 'https://placehold.co/600x400/E4DFD8/3A4E6B/png?text=symmetry', 'https://dict.youdao.com/dictvoice?audio=symmetry&type=2');

INSERT INTO vocab_word_book_words (word_book_id, word_id, sort_order) VALUES
(1, 1, 1), (1, 2, 2), (1, 3, 3), (1, 4, 4), (1, 5, 5),
(1, 6, 6), (1, 7, 7), (1, 8, 8), (1, 9, 9), (1, 10, 10),
(1, 11, 11), (1, 12, 12), (1, 13, 13), (1, 14, 14), (1, 15, 15),
(2, 16, 1), (2, 17, 2), (2, 18, 3), (2, 19, 4), (2, 20, 5),
(2, 21, 6), (2, 22, 7), (2, 23, 8), (2, 24, 9), (2, 25, 10),
(2, 26, 11), (2, 27, 12), (2, 28, 13), (2, 29, 14), (2, 30, 15),
(3, 31, 1), (3, 32, 2), (3, 33, 3), (3, 34, 4), (3, 35, 5),
(3, 36, 6), (3, 37, 7), (3, 38, 8), (3, 39, 9), (3, 40, 10),
(3, 41, 11), (3, 42, 12), (3, 43, 13), (3, 44, 14), (3, 45, 15),
(4, 46, 1), (4, 47, 2), (4, 48, 3), (4, 49, 4), (4, 50, 5),
(4, 51, 6), (4, 52, 7), (4, 53, 8), (4, 54, 9), (4, 55, 10),
(4, 56, 11), (4, 57, 12), (4, 58, 13), (4, 59, 14), (4, 60, 15),
(5, 61, 1), (5, 62, 2), (5, 63, 3), (5, 64, 4), (5, 65, 5),
(5, 66, 6), (5, 67, 7), (5, 68, 8), (5, 69, 9), (5, 70, 10),
(5, 71, 11), (5, 72, 12), (5, 73, 13), (5, 74, 14), (5, 75, 15),
(6, 76, 1), (6, 77, 2), (6, 78, 3), (6, 79, 4), (6, 80, 5),
(6, 81, 6), (6, 82, 7), (6, 83, 8), (6, 84, 9), (6, 85, 10),
(6, 86, 11), (6, 87, 12), (6, 88, 13), (6, 89, 14), (6, 90, 15);

INSERT INTO vocab_user_wordbook_selections (selection_id, user_id, word_book_id) VALUES
(1, 1, 1),
(2, 1, 2);

INSERT INTO vocab_sessions (session_id, user_id, mode_minutes, status, selected_books_snapshot, total_steps, correct_first_try, started_at, completed_at) VALUES
(1, 1, 5, 'completed', JSON_ARRAY(1, 2), 3, 2, '2026-03-23 10:00:00', '2026-03-23 10:04:00');

INSERT INTO vocab_session_items (session_item_id, session_id, word_id, item_type, step_order, prompt_data, options_data, correct_answer, attempt_count, first_attempt_correct, completed_at) VALUES
(1, 1, 2, 'image', 1, JSON_OBJECT('meaning_en', 'n. a formal talk given to students, especially at a university'), JSON_ARRAY('library', 'cafeteria', 'lecture', 'network'), 'lecture', 1, 1, '2026-03-23 10:01:00'),
(2, 1, 17, 'fill', 2, JSON_OBJECT('masked_word', 'var___le', 'meaning_en', 'n. a named value in a program that can change'), NULL, 'iab', 1, 1, '2026-03-23 10:02:00'),
(3, 1, 4, 'sentence_pick', 3, JSON_OBJECT('sentence', 'I need to finish my ________ by Friday.'), JSON_ARRAY('lecture', 'assignment', 'network', 'theorem'), 'assignment', 2, 0, '2026-03-23 10:03:00');

INSERT INTO vocab_session_responses (response_id, session_item_id, user_id, response_text, is_correct, attempt_no, answered_at) VALUES
(1, 1, 1, 'lecture', 1, 1, '2026-03-23 10:01:00'),
(2, 2, 1, 'iab', 1, 1, '2026-03-23 10:02:00'),
(3, 3, 1, 'lecture', 0, 1, '2026-03-23 10:02:30'),
(4, 3, 1, 'assignment', 1, 2, '2026-03-23 10:03:00');

INSERT INTO vocab_user_word_progress (user_id, word_id, times_seen, correct_count, wrong_count, first_try_correct_count, mastery_status, status_set_at, last_session_id, last_practiced_at) VALUES
(1, 2, 1, 1, 0, 1, 'learning', '2026-03-23 10:01:10', 1, '2026-03-23 10:01:00'),
(1, 4, 1, 1, 1, 0, 'forgot', '2026-03-23 10:03:10', 1, '2026-03-23 10:03:00'),
(1, 17, 1, 1, 0, 1, 'mastered', '2026-03-23 10:02:10', 1, '2026-03-23 10:02:00');

SET FOREIGN_KEY_CHECKS = 1;
