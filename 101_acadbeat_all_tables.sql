-- Academic English Practice Platform Schema + Vocabulary Module
-- MySQL 8.0+
CREATE DATABASE IF NOT EXISTS acadbeat DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acadbeat;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS vocab_session_responses;
DROP TABLE IF EXISTS vocab_session_items;
DROP TABLE IF EXISTS vocab_sessions;
DROP TABLE IF EXISTS vocab_user_word_progress;
DROP TABLE IF EXISTS vocab_user_wordbook_selections;
DROP TABLE IF EXISTS vocab_word_book_words;
DROP TABLE IF EXISTS vocab_words;
DROP TABLE IF EXISTS vocab_word_books;

DROP TABLE IF EXISTS checkin_records;
DROP TABLE IF EXISTS checkin_partnerships;

DROP TABLE IF EXISTS forum_post_favorites;
DROP TABLE IF EXISTS forum_post_likes;
DROP TABLE IF EXISTS challenge_team_invites;
DROP TABLE IF EXISTS challenge_team_members;
DROP TABLE IF EXISTS challenge_teams;
DROP TABLE IF EXISTS challenge_meta;
DROP TABLE IF EXISTS challenge_team_join_requests;
DROP TABLE IF EXISTS challenge_team_public_listings;
DROP TABLE IF EXISTS message_center_notice_reads;
DROP TABLE IF EXISTS message_center_notifications;
DROP TABLE IF EXISTS message_center_system_notices;
DROP TABLE IF EXISTS chat_message_media;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversation_members;
DROP TABLE IF EXISTS chat_conversations;

DROP TABLE IF EXISTS forum_post_labels;
DROP TABLE IF EXISTS forum_labels;
DROP TABLE IF EXISTS forum_comment_media;
DROP TABLE IF EXISTS forum_comments;
DROP TABLE IF EXISTS forum_post_media;
DROP TABLE IF EXISTS forum_posts;

DROP TABLE IF EXISTS training_responses;
DROP TABLE IF EXISTS training_attempts;
DROP TABLE IF EXISTS training_item_configs;
DROP TABLE IF EXISTS training_items;
DROP TABLE IF EXISTS training_modules;

DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================
-- 1. users
-- =========================================
CREATE TABLE users (
    user_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_users_username UNIQUE (username),
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_role CHECK (role IN ('user', 'admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 2. training_modules
-- =========================================
CREATE TABLE training_modules (
    module_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    skill_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_training_modules_skill_type
        CHECK (skill_type IN ('listening', 'speaking', 'integrated')),

    CONSTRAINT chk_training_modules_status
        CHECK (status IN ('draft', 'published', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 3. training_items
-- =========================================
CREATE TABLE training_items (
    item_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    module_id BIGINT NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NULL,
    prompt_text TEXT NULL,
    order_index INT NOT NULL,
    points INT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_training_items_module
        FOREIGN KEY (module_id)
        REFERENCES training_modules(module_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_training_items_module_order
        UNIQUE (module_id, order_index),

    CONSTRAINT chk_training_items_points
        CHECK (points IS NULL OR points >= 0),

    CONSTRAINT chk_training_items_item_type
        CHECK (item_type IN ('audio_comprehension', 'listen_retell'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 4. training_item_configs
-- =========================================
CREATE TABLE training_item_configs (
    config_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    item_id BIGINT NOT NULL,
    content_data JSON NOT NULL,
    answer_data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_training_item_configs_item
        FOREIGN KEY (item_id)
        REFERENCES training_items(item_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_training_item_configs_item UNIQUE (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 5. training_attempts
-- =========================================
CREATE TABLE training_attempts (
    attempt_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    module_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    total_score DECIMAL(6,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_training_attempts_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_training_attempts_module
        FOREIGN KEY (module_id)
        REFERENCES training_modules(module_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_training_attempts_status
        CHECK (status IN ('in_progress', 'submitted', 'graded')),

    CONSTRAINT chk_training_attempts_total_score
        CHECK (total_score IS NULL OR total_score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 6. training_responses
-- =========================================
CREATE TABLE training_responses (
    response_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    attempt_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    response_data JSON NOT NULL,
    score DECIMAL(6,2) NULL,
    submitted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_training_responses_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES training_attempts(attempt_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_training_responses_item
        FOREIGN KEY (item_id)
        REFERENCES training_items(item_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_training_responses_attempt_item
        UNIQUE (attempt_id, item_id),

    CONSTRAINT chk_training_responses_score
        CHECK (score IS NULL OR score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 7. forum_posts
-- =========================================
CREATE TABLE forum_posts (
    post_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content_text MEDIUMTEXT NULL,
    view_count INT NOT NULL DEFAULT 0,
    comment_count INT NOT NULL DEFAULT 0,
    like_count INT NOT NULL DEFAULT 0,
    favorite_count INT NOT NULL DEFAULT 0,
    last_commented_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_forum_posts_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_forum_posts_status
        CHECK (status IN ('active', 'hidden', 'deleted', 'Under review', 'Rejected')),

    CONSTRAINT chk_forum_posts_view_count
        CHECK (view_count >= 0),

    CONSTRAINT chk_forum_posts_comment_count
        CHECK (comment_count >= 0),

    CONSTRAINT chk_forum_posts_like_count
        CHECK (like_count >= 0),

    CONSTRAINT chk_forum_posts_favorite_count
        CHECK (favorite_count >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 8. forum_post_media
-- =========================================
CREATE TABLE forum_post_media (
    media_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    media_type VARCHAR(20) NOT NULL,
    media_url MEDIUMTEXT NOT NULL,
    order_index INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_forum_post_media_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_forum_post_media_type
        CHECK (media_type IN ('image', 'video', 'audio', 'link')),

    CONSTRAINT chk_forum_post_media_order
        CHECK (order_index > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 9. forum_comments
-- =========================================
CREATE TABLE forum_comments (
    comment_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    parent_comment_id BIGINT NULL,
    content_text MEDIUMTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_forum_comments_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_forum_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_forum_comments_parent
        FOREIGN KEY (parent_comment_id)
        REFERENCES forum_comments(comment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_forum_comments_status
        CHECK (status IN ('active', 'hidden', 'deleted'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 10. forum_comment_media
-- =========================================
CREATE TABLE forum_comment_media (
    comment_media_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    comment_id BIGINT NOT NULL,
    media_type VARCHAR(20) NOT NULL,
    media_url MEDIUMTEXT NOT NULL,
    order_index INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_forum_comment_media_comment
        FOREIGN KEY (comment_id)
        REFERENCES forum_comments(comment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_forum_comment_media_type
        CHECK (media_type IN ('image', 'video', 'audio', 'link')),

    CONSTRAINT chk_forum_comment_media_order
        CHECK (order_index > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 11. forum_labels
-- =========================================
CREATE TABLE forum_labels (
    label_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_forum_labels_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 12. forum_post_labels
-- =========================================
CREATE TABLE forum_post_labels (
    post_label_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    label_id BIGINT NOT NULL,

    CONSTRAINT fk_forum_post_labels_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_forum_post_labels_label
        FOREIGN KEY (label_id)
        REFERENCES forum_labels(label_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_forum_post_labels_post_label UNIQUE (post_id, label_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 13. checkin_partnerships
-- =========================================
CREATE TABLE checkin_partnerships (
    partnership_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_one_id BIGINT NOT NULL,
    user_two_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_checkin_partnerships_user_one
        FOREIGN KEY (user_one_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_checkin_partnerships_user_two
        FOREIGN KEY (user_two_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_checkin_partnerships_status
        CHECK (status IN ('active', 'ended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 14. checkin_records
-- =========================================
CREATE TABLE checkin_records (
    record_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    partnership_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    checkin_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_checkin_records_partnership
        FOREIGN KEY (partnership_id)
        REFERENCES checkin_partnerships(partnership_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_checkin_records_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_checkin_records_unique_daily
        UNIQUE (partnership_id, user_id, checkin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 15. chat_conversations
-- =========================================
CREATE TABLE chat_conversations (
    conversation_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_type VARCHAR(20) NOT NULL DEFAULT 'direct',
    title VARCHAR(160) NULL,
    created_by_user_id BIGINT NOT NULL,
    last_message_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_chat_conversations_created_by
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_chat_conversations_type
        CHECK (conversation_type IN ('direct', 'group')),

    CONSTRAINT chk_chat_conversations_status
        CHECK (status IN ('active', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 16. chat_conversation_members
-- =========================================
CREATE TABLE chat_conversation_members (
    conversation_member_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    last_read_at DATETIME NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_chat_conversation_members_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(conversation_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_chat_conversation_members_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_chat_conversation_members_unique
        UNIQUE (conversation_id, user_id),

    CONSTRAINT chk_chat_conversation_members_role
        CHECK (member_role IN ('owner', 'member'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 17. chat_messages
-- =========================================
CREATE TABLE chat_messages (
    message_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    content_text MEDIUMTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_chat_messages_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(conversation_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_chat_messages_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_chat_messages_status
        CHECK (status IN ('active', 'deleted'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 18. chat_message_media
-- =========================================
CREATE TABLE chat_message_media (
    chat_message_media_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    message_id BIGINT NOT NULL,
    media_type VARCHAR(20) NOT NULL,
    media_url MEDIUMTEXT NOT NULL,
    order_index INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_chat_message_media_message
        FOREIGN KEY (message_id)
        REFERENCES chat_messages(message_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_chat_message_media_type
        CHECK (media_type IN ('image', 'video', 'audio', 'link')),

    CONSTRAINT chk_chat_message_media_order
        CHECK (order_index > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 19. vocab_word_books
-- =========================================
CREATE TABLE vocab_word_books (
    word_book_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(32) NOT NULL,
    title VARCHAR(128) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_vocab_word_books_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 20. vocab_words
-- =========================================
CREATE TABLE vocab_words (
    word_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    word VARCHAR(64) NOT NULL,
    phonetic VARCHAR(128) NULL,
    meaning_en VARCHAR(512) NOT NULL,
    meaning_zh VARCHAR(512) NULL,
    sentence TEXT NULL,
    image_url VARCHAR(1000) NULL,
    audio_url VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 21. vocab_word_book_words
-- =========================================
CREATE TABLE vocab_word_book_words (
    word_book_id BIGINT NOT NULL,
    word_id BIGINT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,

    PRIMARY KEY (word_book_id, word_id),

    CONSTRAINT fk_vocab_word_book_words_book
        FOREIGN KEY (word_book_id)
        REFERENCES vocab_word_books(word_book_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_vocab_word_book_words_word
        FOREIGN KEY (word_id)
        REFERENCES vocab_words(word_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 22. vocab_user_wordbook_selections
-- =========================================
CREATE TABLE vocab_user_wordbook_selections (
    selection_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    word_book_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_vocab_user_wordbook_selections_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_vocab_user_wordbook_selections_book
        FOREIGN KEY (word_book_id)
        REFERENCES vocab_word_books(word_book_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_vocab_user_wordbook_selection UNIQUE (user_id, word_book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 23. vocab_sessions
-- =========================================
CREATE TABLE vocab_sessions (
    session_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    mode_minutes INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    selected_books_snapshot JSON NULL,
    total_steps INT NOT NULL DEFAULT 0,
    correct_first_try INT NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_vocab_sessions_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_vocab_sessions_mode_minutes
        CHECK (mode_minutes IN (1, 5, 10)),

    CONSTRAINT chk_vocab_sessions_status
        CHECK (status IN ('in_progress', 'completed', 'abandoned'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 24. vocab_session_items
-- =========================================
CREATE TABLE vocab_session_items (
    session_item_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT NOT NULL,
    word_id BIGINT NOT NULL,
    item_type VARCHAR(30) NOT NULL,
    step_order INT NOT NULL,
    prompt_data JSON NULL,
    options_data JSON NULL,
    correct_answer VARCHAR(255) NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    first_attempt_correct TINYINT(1) NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_vocab_session_items_session
        FOREIGN KEY (session_id)
        REFERENCES vocab_sessions(session_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_vocab_session_items_word
        FOREIGN KEY (word_id)
        REFERENCES vocab_words(word_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_vocab_session_items_step UNIQUE (session_id, step_order),

    CONSTRAINT chk_vocab_session_items_type
        CHECK (item_type IN ('learn', 'image', 'audio', 'fill', 'sentence_fill', 'sentence_pick'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 25. vocab_session_responses
-- =========================================
CREATE TABLE vocab_session_responses (
    response_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_item_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    response_text VARCHAR(255) NULL,
    is_correct TINYINT(1) NOT NULL,
    attempt_no INT NOT NULL DEFAULT 1,
    answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_vocab_session_responses_item
        FOREIGN KEY (session_item_id)
        REFERENCES vocab_session_items(session_item_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_vocab_session_responses_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_vocab_session_responses_attempt UNIQUE (session_item_id, attempt_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 26. vocab_user_word_progress
-- =========================================
CREATE TABLE vocab_user_word_progress (
    user_id BIGINT NOT NULL,
    word_id BIGINT NOT NULL,
    times_seen INT NOT NULL DEFAULT 0,
    correct_count INT NOT NULL DEFAULT 0,
    wrong_count INT NOT NULL DEFAULT 0,
    first_try_correct_count INT NOT NULL DEFAULT 0,
    mastery_status VARCHAR(20) NOT NULL DEFAULT 'new',
    status_set_at DATETIME NULL,
    last_session_id BIGINT NULL,
    last_practiced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, word_id),

    CONSTRAINT fk_vocab_user_word_progress_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_vocab_user_word_progress_word
        FOREIGN KEY (word_id)
        REFERENCES vocab_words(word_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_vocab_user_word_progress_session
        FOREIGN KEY (last_session_id)
        REFERENCES vocab_sessions(session_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT chk_vocab_user_word_progress_mastery_status
        CHECK (mastery_status IN ('new', 'learning', 'mastered', 'forgot'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 20. forum_post_likes
-- =========================================
CREATE TABLE forum_post_likes (
    like_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_forum_post_likes UNIQUE (post_id, user_id),

    CONSTRAINT fk_forum_post_likes_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_forum_post_likes_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 21. forum_post_favorites
-- =========================================
CREATE TABLE forum_post_favorites (
    favorite_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_forum_post_favorites UNIQUE (post_id, user_id),

    CONSTRAINT fk_forum_post_favorites_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_forum_post_favorites_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 22. message_center_notifications
-- =========================================
CREATE TABLE message_center_notifications (
    notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    recipient_user_id BIGINT NOT NULL,
    actor_user_id BIGINT NULL,
    notification_type ENUM('reply', 'like', 'favorite', 'challenge_reset') NOT NULL,
    post_id BIGINT NULL,
    comment_id BIGINT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    body_text TEXT NULL,
    cta_label VARCHAR(80) NULL,
    cta_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_message_center_notifications_recipient
        FOREIGN KEY (recipient_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_message_center_notifications_actor
        FOREIGN KEY (actor_user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_message_center_notifications_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_message_center_notifications_comment
        FOREIGN KEY (comment_id)
        REFERENCES forum_comments(comment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 23. challenge_meta
-- =========================================
CREATE TABLE challenge_meta (
    meta_key VARCHAR(100) PRIMARY KEY,
    meta_value VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 24. challenge_teams
-- =========================================
CREATE TABLE challenge_teams (
    team_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    team_name VARCHAR(120) NOT NULL,
    captain_user_id BIGINT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    daily_rank INT NULL,
    rank_updated_on DATE NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,

    CONSTRAINT chk_challenge_teams_score CHECK (score >= 0),

    CONSTRAINT fk_challenge_teams_captain
        FOREIGN KEY (captain_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 25. challenge_team_members
-- =========================================
CREATE TABLE challenge_team_members (
    team_member_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    member_role ENUM('captain', 'member') NOT NULL DEFAULT 'member',
    membership_status ENUM('active', 'removed') NOT NULL DEFAULT 'active',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_challenge_team_member UNIQUE (team_id, user_id),

    CONSTRAINT fk_challenge_team_members_team
        FOREIGN KEY (team_id)
        REFERENCES challenge_teams(team_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_challenge_team_members_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 26. challenge_team_invites
-- =========================================
CREATE TABLE challenge_team_invites (
    invite_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL,
    week_start_date DATE NOT NULL,
    inviter_user_id BIGINT NOT NULL,
    invitee_user_id BIGINT NOT NULL,
    invitee_username VARCHAR(50) NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    responded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_challenge_team_invites_team
        FOREIGN KEY (team_id)
        REFERENCES challenge_teams(team_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_challenge_team_invites_inviter
        FOREIGN KEY (inviter_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_challenge_team_invites_invitee
        FOREIGN KEY (invitee_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 27. message_center_system_notices
-- =========================================
-- 27. challenge_team_public_listings
-- =========================================
CREATE TABLE challenge_team_public_listings (
    listing_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL,
    week_start_date DATE NOT NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    description_text VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_challenge_team_public_listing UNIQUE (team_id, week_start_date),

    CONSTRAINT fk_challenge_team_public_listings_team
        FOREIGN KEY (team_id)
        REFERENCES challenge_teams(team_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 28. challenge_team_join_requests
-- =========================================
CREATE TABLE challenge_team_join_requests (
    join_request_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL,
    week_start_date DATE NOT NULL,
    requester_user_id BIGINT NOT NULL,
    captain_user_id BIGINT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    message_text VARCHAR(255) NULL,
    responded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_challenge_team_join_requests_team
        FOREIGN KEY (team_id)
        REFERENCES challenge_teams(team_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_challenge_team_join_requests_requester
        FOREIGN KEY (requester_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_challenge_team_join_requests_captain
        FOREIGN KEY (captain_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 29. message_center_system_notices
-- =========================================
CREATE TABLE message_center_system_notices (
    notice_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    body_text TEXT NOT NULL,
    cta_label VARCHAR(80) NULL,
    cta_url VARCHAR(255) NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- 30. message_center_notice_reads
-- =========================================
CREATE TABLE message_center_notice_reads (
    notice_read_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    notice_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_message_center_notice_reads UNIQUE (notice_id, user_id),

    CONSTRAINT fk_message_center_notice_reads_notice
        FOREIGN KEY (notice_id)
        REFERENCES message_center_system_notices(notice_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_message_center_notice_reads_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================
-- Indexes
-- =========================================

CREATE INDEX idx_training_items_module_id
    ON training_items(module_id);

CREATE INDEX idx_training_attempts_user_id
    ON training_attempts(user_id);

CREATE INDEX idx_training_attempts_module_id
    ON training_attempts(module_id);

CREATE INDEX idx_training_responses_attempt_id
    ON training_responses(attempt_id);

CREATE INDEX idx_training_responses_item_id
    ON training_responses(item_id);

CREATE INDEX idx_forum_posts_user_id
    ON forum_posts(user_id);

CREATE INDEX idx_forum_posts_last_commented_at
    ON forum_posts(last_commented_at);

CREATE INDEX idx_forum_posts_created_at
    ON forum_posts(created_at);

CREATE INDEX idx_forum_post_media_post_id
    ON forum_post_media(post_id);

CREATE INDEX idx_forum_comments_post_id
    ON forum_comments(post_id);

CREATE INDEX idx_forum_comments_user_id
    ON forum_comments(user_id);

CREATE INDEX idx_forum_comments_parent_comment_id
    ON forum_comments(parent_comment_id);

CREATE INDEX idx_forum_comment_media_comment_id
    ON forum_comment_media(comment_id);

CREATE INDEX idx_forum_post_labels_post_id
    ON forum_post_labels(post_id);

CREATE INDEX idx_forum_post_labels_label_id
    ON forum_post_labels(label_id);

CREATE INDEX idx_forum_post_likes_post_id
    ON forum_post_likes(post_id);

CREATE INDEX idx_forum_post_likes_user_id
    ON forum_post_likes(user_id);

CREATE INDEX idx_forum_post_favorites_post_id
    ON forum_post_favorites(post_id);

CREATE INDEX idx_forum_post_favorites_user_id
    ON forum_post_favorites(user_id);

CREATE INDEX idx_message_center_notifications_recipient
    ON message_center_notifications(recipient_user_id);

CREATE INDEX idx_message_center_notifications_type
    ON message_center_notifications(notification_type);

CREATE INDEX idx_message_center_notifications_is_read
    ON message_center_notifications(is_read);

CREATE INDEX idx_message_center_notifications_created_at
    ON message_center_notifications(created_at);

CREATE INDEX idx_challenge_teams_week_status
    ON challenge_teams(week_start_date, status);

CREATE INDEX idx_challenge_teams_score_rank
    ON challenge_teams(week_start_date, status, score, daily_rank);

CREATE INDEX idx_challenge_team_members_team_status
    ON challenge_team_members(team_id, membership_status);

CREATE INDEX idx_challenge_team_members_user_status
    ON challenge_team_members(user_id, membership_status);

CREATE INDEX idx_challenge_team_invites_invitee_status
    ON challenge_team_invites(invitee_user_id, week_start_date, status);

CREATE INDEX idx_challenge_team_invites_team_status
    ON challenge_team_invites(team_id, week_start_date, status);

CREATE INDEX idx_challenge_team_public_listings_week_status
    ON challenge_team_public_listings(week_start_date, status);

CREATE INDEX idx_challenge_team_join_requests_captain_status
    ON challenge_team_join_requests(captain_user_id, week_start_date, status);

CREATE INDEX idx_challenge_team_join_requests_requester_status
    ON challenge_team_join_requests(requester_user_id, week_start_date, status);

CREATE INDEX idx_message_center_system_notices_status
    ON message_center_system_notices(status);

CREATE INDEX idx_message_center_notice_reads_user_id
    ON message_center_notice_reads(user_id);

CREATE INDEX idx_checkin_partnerships_user_one_id
    ON checkin_partnerships(user_one_id);

CREATE INDEX idx_checkin_partnerships_user_two_id
    ON checkin_partnerships(user_two_id);

CREATE INDEX idx_checkin_records_partnership_id
    ON checkin_records(partnership_id);

CREATE INDEX idx_checkin_records_user_id
    ON checkin_records(user_id);

CREATE INDEX idx_chat_conversations_created_by_user_id
    ON chat_conversations(created_by_user_id);

CREATE INDEX idx_chat_conversations_last_message_at
    ON chat_conversations(last_message_at);

CREATE INDEX idx_chat_conversation_members_conversation_id
    ON chat_conversation_members(conversation_id);

CREATE INDEX idx_chat_conversation_members_user_id
    ON chat_conversation_members(user_id);

CREATE INDEX idx_chat_messages_conversation_id
    ON chat_messages(conversation_id);

CREATE INDEX idx_chat_messages_user_id
    ON chat_messages(user_id);

CREATE INDEX idx_chat_messages_created_at
    ON chat_messages(created_at);

CREATE INDEX idx_chat_message_media_message_id
    ON chat_message_media(message_id);

CREATE INDEX idx_vocab_words_word
    ON vocab_words(word);

CREATE INDEX idx_vocab_word_book_words_word_id
    ON vocab_word_book_words(word_id);

CREATE INDEX idx_vocab_user_wordbook_selections_user_id
    ON vocab_user_wordbook_selections(user_id);

CREATE INDEX idx_vocab_sessions_user_id
    ON vocab_sessions(user_id);

CREATE INDEX idx_vocab_sessions_status
    ON vocab_sessions(status);

CREATE INDEX idx_vocab_session_items_session_id
    ON vocab_session_items(session_id);

CREATE INDEX idx_vocab_session_items_word_id
    ON vocab_session_items(word_id);

CREATE INDEX idx_vocab_session_responses_user_id
    ON vocab_session_responses(user_id);

CREATE INDEX idx_vocab_session_responses_item_id
    ON vocab_session_responses(session_item_id);

CREATE INDEX idx_vocab_user_word_progress_last_session_id
    ON vocab_user_word_progress(last_session_id);
