-- Consolidated schema file: all CREATE TABLE statements live here.
-- Generated from the previous split SQL files for a single schema bootstrap entrypoint.

-- Academic English Practice Platform Schema + Vocabulary Module
-- MySQL 8.0+
CREATE DATABASE IF NOT EXISTS acadbeat DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acadbeat;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS chat_draw_guess_guesses;
DROP TABLE IF EXISTS chat_draw_guess_strokes;
DROP TABLE IF EXISTS chat_draw_guess_rounds;
DROP TABLE IF EXISTS chat_draw_guess_players;
DROP TABLE IF EXISTS chat_draw_guess_words;
DROP TABLE IF EXISTS chat_draw_guess_games;

DROP TABLE IF EXISTS peer_video_session_events;
DROP TABLE IF EXISTS peer_video_match_queue;
DROP TABLE IF EXISTS peer_video_sessions;
DROP TABLE IF EXISTS peer_video_match_settings;
DROP TABLE IF EXISTS peer_space_members;
DROP TABLE IF EXISTS peer_spaces;

DROP TABLE IF EXISTS video_resources;
DROP TABLE IF EXISTS forum_announcements;

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
DROP TABLE IF EXISTS challenge_signups;
DROP TABLE IF EXISTS challenge_team_public_listings;
DROP TABLE IF EXISTS message_center_notice_reads;
DROP TABLE IF EXISTS message_center_notifications;
DROP TABLE IF EXISTS message_center_system_notices;
DROP TABLE IF EXISTS announcement_reads;
DROP TABLE IF EXISTS announcements;
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

-- ===== Source: 101_acadbeat_core_tables.sql =====
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
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
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
CREATE TABLE forum_labels (
    label_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_forum_labels_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE chat_conversation_members (
    conversation_member_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    last_read_at DATETIME NULL,
    last_read_message_id BIGINT NULL,
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
CREATE TABLE vocab_word_books (
    word_book_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(32) NOT NULL,
    title VARCHAR(128) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_vocab_word_books_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE message_center_notifications (
    notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    recipient_user_id BIGINT NOT NULL,
    actor_user_id BIGINT NULL,
    notification_type ENUM('reply', 'like', 'favorite', 'challenge_reset', 'system') NOT NULL,
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
CREATE TABLE challenge_meta (
    meta_key VARCHAR(100) PRIMARY KEY,
    meta_value VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE challenge_signups (
    signup_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    week_start_date DATE NOT NULL,
    user_id BIGINT NOT NULL,
    signup_status ENUM('active', 'withdrawn') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_challenge_signup_week_user UNIQUE (week_start_date, user_id),

    CONSTRAINT fk_challenge_signups_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE challenge_teams (
    team_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    team_name VARCHAR(120) NOT NULL,
    captain_user_id BIGINT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    daily_rank INT NULL,
    rank_updated_on DATE NULL,
    status ENUM('forming', 'locked', 'expired', 'archived') NOT NULL DEFAULT 'forming',
    expires_at DATETIME NOT NULL,
    locked_at DATETIME NULL,
    team_name_confirmed_at DATETIME NULL,
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
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    author VARCHAR(100) NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    status ENUM('draft', 'published') DEFAULT 'published',
    view_count INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_announcement_reads_user_announcement
        UNIQUE (user_id, announcement_id),

    CONSTRAINT fk_announcement_reads_announcement
        FOREIGN KEY (announcement_id)
        REFERENCES announcements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Source: 105_academic_practice_video_match_tables.sql =====
CREATE TABLE IF NOT EXISTS peer_spaces (
    space_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_type VARCHAR(30) NOT NULL,
    created_by_user_id BIGINT NOT NULL,
    title VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    max_members INT NOT NULL DEFAULT 2,
    activated_at DATETIME NULL,
    ended_at DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_spaces_creator
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_peer_spaces_type
        CHECK (space_type IN ('voice_room')),

    CONSTRAINT chk_peer_spaces_status
        CHECK (status IN ('pending', 'active', 'cancelled', 'completed', 'expired')),

    CONSTRAINT chk_peer_spaces_max_members
        CHECK (max_members >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS peer_space_members (
    membership_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    membership_status VARCHAR(20) NOT NULL DEFAULT 'accepted',
    invited_by_user_id BIGINT NULL,
    responded_at DATETIME NULL,
    joined_at DATETIME NULL,
    left_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_space_members_space
        FOREIGN KEY (space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_space_members_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_space_members_inviter
        FOREIGN KEY (invited_by_user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_space_member_unique
        UNIQUE (space_id, user_id),

    CONSTRAINT chk_peer_space_members_role
        CHECK (member_role IN ('owner', 'member', 'guest')),

    CONSTRAINT chk_peer_space_members_status
        CHECK (membership_status IN ('pending', 'accepted', 'left', 'removed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS peer_video_match_settings (
    user_id BIGINT PRIMARY KEY,
    feature_enabled TINYINT(1) NOT NULL DEFAULT 1,
    auto_match_enabled TINYINT(1) NOT NULL DEFAULT 1,
    preferred_mode VARCHAR(30) NOT NULL DEFAULT 'random_1v1',
    camera_enabled TINYINT(1) NOT NULL DEFAULT 1,
    microphone_enabled TINYINT(1) NOT NULL DEFAULT 1,
    blocked_until DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_match_settings_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_peer_video_match_settings_mode
        CHECK (preferred_mode IN ('random_1v1')),

    CONSTRAINT chk_peer_video_match_settings_feature_enabled
        CHECK (feature_enabled IN (0, 1)),

    CONSTRAINT chk_peer_video_match_settings_auto_match_enabled
        CHECK (auto_match_enabled IN (0, 1)),

    CONSTRAINT chk_peer_video_match_settings_camera_enabled
        CHECK (camera_enabled IN (0, 1)),

    CONSTRAINT chk_peer_video_match_settings_microphone_enabled
        CHECK (microphone_enabled IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS peer_video_sessions (
    session_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    room_id VARCHAR(128) NOT NULL,
    queue_mode VARCHAR(30) NOT NULL DEFAULT 'random_1v1',
    user_one_id BIGINT NOT NULL,
    user_two_id BIGINT NOT NULL,
    matched_by VARCHAR(20) NOT NULL DEFAULT 'system',
    status VARCHAR(20) NOT NULL DEFAULT 'matched',
    matched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    last_activity_at DATETIME NULL,
    ended_reason VARCHAR(30) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_sessions_space
        FOREIGN KEY (space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_sessions_user_one
        FOREIGN KEY (user_one_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_sessions_user_two
        FOREIGN KEY (user_two_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_video_sessions_space UNIQUE (space_id),
    CONSTRAINT uq_peer_video_sessions_room UNIQUE (room_id),

    CONSTRAINT chk_peer_video_sessions_mode
        CHECK (queue_mode IN ('random_1v1')),

    CONSTRAINT chk_peer_video_sessions_matched_by
        CHECK (matched_by IN ('system', 'manual')),

    CONSTRAINT chk_peer_video_sessions_status
        CHECK (status IN ('matched', 'connecting', 'active', 'ended', 'cancelled', 'expired')),

    CONSTRAINT chk_peer_video_sessions_end_reason
        CHECK (ended_reason IS NULL OR ended_reason IN ('user_left', 'cancelled', 'timeout', 'system'))

    -- NOTE:
    -- MySQL 8.0 can reject CHECK constraints on columns that are also used
    -- in FK referential actions in some server variants/configurations.
    -- Keep this rule enforced at application level for compatibility.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS peer_video_match_queue (
    queue_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    queue_mode VARCHAR(30) NOT NULL DEFAULT 'random_1v1',
    status VARCHAR(20) NOT NULL DEFAULT 'waiting',
    request_token CHAR(36) NOT NULL,
    current_session_id BIGINT NULL,
    joined_space_id BIGINT NULL,
    enqueued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    matched_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_match_queue_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_match_queue_session
        FOREIGN KEY (current_session_id)
        REFERENCES peer_video_sessions(session_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_match_queue_space
        FOREIGN KEY (joined_space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_video_match_queue_user UNIQUE (user_id),
    CONSTRAINT uq_peer_video_match_queue_request_token UNIQUE (request_token),

    CONSTRAINT chk_peer_video_match_queue_mode
        CHECK (queue_mode IN ('random_1v1')),

    CONSTRAINT chk_peer_video_match_queue_status
        CHECK (status IN ('waiting', 'matched', 'cancelled', 'expired', 'idle'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS peer_video_session_events (
    event_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT NOT NULL,
    actor_user_id BIGINT NULL,
    event_type VARCHAR(30) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_session_events_session
        FOREIGN KEY (session_id)
        REFERENCES peer_video_sessions(session_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_session_events_actor
        FOREIGN KEY (actor_user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT chk_peer_video_session_events_type
        CHECK (event_type IN (
            'queue_joined',
            'queue_cancelled',
            'matched',
            'room_opened',
            'joined',
            'left',
            'heartbeat',
            'ended',
            'expired'
        ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Source: 210_academic_practice_video_resources.sql =====
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

-- ===== Source: 220_forum_announcements.sql =====
CREATE TABLE IF NOT EXISTS forum_announcements (
  announcement_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(512) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id),
  KEY idx_forum_announcements_active_pinned_created (is_active, is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Source: 230_chat_draw_guess_game_tables.sql =====
CREATE TABLE IF NOT EXISTS chat_draw_guess_games (
    game_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'LOBBY',
    min_players INT NOT NULL DEFAULT 3,
    round_index INT NOT NULL DEFAULT 0,
    current_round_id BIGINT NULL,
    current_drawer_user_id BIGINT NULL,
    current_word_id BIGINT NULL,
    current_word VARCHAR(96) NULL,
    current_text_hint VARCHAR(255) NULL,
    current_second_text_hint VARCHAR(255) NULL,
    current_hint_stage VARCHAR(20) NOT NULL DEFAULT 'none',
    text_hint_revealed_at DATETIME NULL,
    second_text_hint_revealed_at DATETIME NULL,
    correct_guess_count INT NOT NULL DEFAULT 0,
    active_guessers_count INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    created_by_user_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_games_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(conversation_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_games_creator
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_draw_guess_players (
    game_player_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    game_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    join_order INT NOT NULL DEFAULT 1,
    drawer_order INT NOT NULL DEFAULT 1,
    score INT NOT NULL DEFAULT 0,
    is_ready TINYINT(1) NOT NULL DEFAULT 0,
    player_status VARCHAR(20) NOT NULL DEFAULT 'active',
    connection_status VARCHAR(20) NOT NULL DEFAULT 'online',
    has_drawn TINYINT(1) NOT NULL DEFAULT 0,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_players_game
        FOREIGN KEY (game_id)
        REFERENCES chat_draw_guess_games(game_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_players_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_draw_guess_players UNIQUE (game_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_draw_guess_words (
    word_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    word_text VARCHAR(96) NOT NULL,
    text_hint VARCHAR(255) NOT NULL,
    second_text_hint VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_draw_guess_words UNIQUE (word_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_draw_guess_rounds (
    round_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    game_id BIGINT NOT NULL,
    round_index INT NOT NULL,
    drawer_user_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ROUND_START',
    candidate_words_json JSON NULL,
    selected_word_id BIGINT NULL,
    selected_word VARCHAR(96) NULL,
    text_hint VARCHAR(255) NULL,
    second_text_hint VARCHAR(255) NULL,
    selection_deadline_at DATETIME NULL,
    started_at DATETIME NULL,
    ends_at DATETIME NULL,
    text_hint_revealed_at DATETIME NULL,
    second_text_hint_revealed_at DATETIME NULL,
    ended_at DATETIME NULL,
    correct_guess_count INT NOT NULL DEFAULT 0,
    active_guessers_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_rounds_game
        FOREIGN KEY (game_id)
        REFERENCES chat_draw_guess_games(game_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_rounds_drawer
        FOREIGN KEY (drawer_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_draw_guess_strokes (
    stroke_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    round_id BIGINT NOT NULL,
    created_by_user_id BIGINT NOT NULL,
    event_type VARCHAR(20) NOT NULL DEFAULT 'stroke',
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_strokes_round
        FOREIGN KEY (round_id)
        REFERENCES chat_draw_guess_rounds(round_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_strokes_user
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_draw_guess_guesses (
    guess_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    round_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    guess_text VARCHAR(255) NOT NULL,
    normalized_guess VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    score_awarded INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_guesses_round
        FOREIGN KEY (round_id)
        REFERENCES chat_draw_guess_rounds(round_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_guesses_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
