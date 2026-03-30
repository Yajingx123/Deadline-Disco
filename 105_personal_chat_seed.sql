-- Personal chat seed data
-- Run this AFTER 104_personal_chat_tables.sql or 101_acadbeat_all_tables.sql
USE acadbeat;
SET NAMES utf8mb4;

INSERT IGNORE INTO users (user_id, username, email, password_hash, avatar_url) VALUES
(1, 'demo_student', 'demo@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL),
(2, 'noura', 'noura@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL),
(3, 'emily', 'emily@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL),
(4, 'samira', 'samira@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL),
(5, 'benjamin', 'benjamin@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL),
(6, 'karen', 'karen@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL),
(7, 'shu', 'shu@acadbeat.local', '$2y$12$uumz1aB0iv66KGi.uLGqhunV4/MqdzYVAKLKOvI14zJqeSaOjdRNy', NULL);

INSERT IGNORE INTO chat_conversations (conversation_id, conversation_type, title, created_by_user_id, last_message_at, status, created_at, updated_at) VALUES
(1, 'direct', NULL, 1, '2026-03-24 09:18:00', 'active', '2026-03-24 09:00:00', '2026-03-24 09:18:00'),
(2, 'direct', NULL, 1, '2026-03-24 12:42:00', 'active', '2026-03-24 12:00:00', '2026-03-24 12:42:00'),
(3, 'group', 'Research Sprint', 1, '2026-03-25 16:25:00', 'active', '2026-03-25 15:30:00', '2026-03-25 16:25:00');

INSERT IGNORE INTO chat_conversation_members (conversation_member_id, conversation_id, user_id, member_role, last_read_at, joined_at, created_at, updated_at) VALUES
(1, 1, 1, 'owner', '2026-03-24 09:18:00', '2026-03-24 09:00:00', '2026-03-24 09:00:00', '2026-03-24 09:18:00'),
(2, 1, 2, 'member', '2026-03-24 09:18:00', '2026-03-24 09:00:00', '2026-03-24 09:00:00', '2026-03-24 09:18:00'),
(3, 2, 1, 'owner', '2026-03-24 12:42:00', '2026-03-24 12:00:00', '2026-03-24 12:00:00', '2026-03-24 12:42:00'),
(4, 2, 3, 'member', '2026-03-24 12:42:00', '2026-03-24 12:00:00', '2026-03-24 12:00:00', '2026-03-24 12:42:00'),
(5, 3, 1, 'owner', '2026-03-25 16:25:00', '2026-03-25 15:30:00', '2026-03-25 15:30:00', '2026-03-25 16:25:00'),
(6, 3, 4, 'member', '2026-03-25 16:25:00', '2026-03-25 15:32:00', '2026-03-25 15:32:00', '2026-03-25 16:25:00'),
(7, 3, 5, 'member', '2026-03-25 16:25:00', '2026-03-25 15:33:00', '2026-03-25 15:33:00', '2026-03-25 16:25:00');

INSERT IGNORE INTO chat_messages (message_id, conversation_id, user_id, content_text, status, created_at, updated_at) VALUES
(1, 1, 2, 'Hi! I found a useful article about discourse markers for tomorrow''s seminar. [Open the resource](https://owl.purdue.edu/owl/general_writing/academic_writing/index.html)', 'active', '2026-03-24 09:03:00', '2026-03-24 09:03:00'),
(2, 1, 1, 'Perfect. I will review it after class and send my notes tonight. 😊', 'active', '2026-03-24 09:18:00', '2026-03-24 09:18:00'),
(3, 2, 3, 'Can you listen to my speaking draft later? I just uploaded the clip. ![audio:sample-feedback.webm](http://127.0.0.1:8001/forum-project/uploads/audio/recording-1774277487228-1223e8bf9466.webm)', 'active', '2026-03-24 12:14:00', '2026-03-24 12:14:00'),
(4, 2, 1, 'Yes. The opening is already strong. I also attached the slide screenshot I mentioned. ![presentation-reference](http://127.0.0.1:8001/forum-project/uploads/image/Screenshot-2026-03-24-004358-505636a13442.png)', 'active', '2026-03-24 12:42:00', '2026-03-24 12:42:00'),
(5, 3, 1, 'Let''s use this group for the research sprint. Drop sources, drafts, or voice notes here.', 'active', '2026-03-25 15:35:00', '2026-03-25 15:35:00'),
(6, 3, 4, 'I added the library report link here: [Campus library usage](https://example.com/library-report)', 'active', '2026-03-25 15:48:00', '2026-03-25 15:48:00'),
(7, 3, 5, 'I will upload the interview audio after class.', 'active', '2026-03-25 16:25:00', '2026-03-25 16:25:00');

INSERT IGNORE INTO chat_message_media (chat_message_media_id, message_id, media_type, media_url, order_index, created_at) VALUES
(1, 1, 'link', 'https://owl.purdue.edu/owl/general_writing/academic_writing/index.html', 1, '2026-03-24 09:03:00'),
(2, 3, 'audio', 'http://127.0.0.1:8001/forum-project/uploads/audio/recording-1774277487228-1223e8bf9466.webm', 1, '2026-03-24 12:14:00'),
(3, 4, 'image', 'http://127.0.0.1:8001/forum-project/uploads/image/Screenshot-2026-03-24-004358-505636a13442.png', 1, '2026-03-24 12:42:00'),
(4, 6, 'link', 'https://example.com/library-report', 1, '2026-03-25 15:48:00');
