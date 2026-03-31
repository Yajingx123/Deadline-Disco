USE acadbeat;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS chat_conversations (
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

CREATE TABLE IF NOT EXISTS chat_conversation_members (
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

CREATE TABLE IF NOT EXISTS chat_messages (
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

CREATE TABLE IF NOT EXISTS chat_message_media (
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
